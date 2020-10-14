<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for paygw_paypal.
 *
 * @package    paygw_paypal
 * @category   privacy
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_paypal\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for paygw_paypal.
 *
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        // Transactions store user data.
        \core_privacy\local\metadata\provider,

        // The paypal payment gateway contains user's transactions.
        \core_privacy\local\request\plugin\provider,

        // This plugin is capable of determining which users have data within it.
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'paypal_js_sdk',
            [
                'clientid' => 'privacy:metadata:clientid',
                'brandname' => 'privacy:metadata:brandname',
            ],
            'privacy:metadata:paypal_js_sdk'
        );

        // The paygw_paypal has a DB table that contains user data.
        $collection->add_database_table(
            'paygw_paypal',
            [
                'pp_orderid' => 'privacy:metadata:paygw_paypal:pp_orderid',
            ],
            'privacy:metadata:paygw_paypal'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT pa.contextid
                  FROM {paygw_paypal} pgp
                  JOIN {payments} p ON pgp.paymentid = p.id
                  JOIN {payment_accounts} pa ON p.accountid = pa.id
                 WHERE p.userid = :userid";

        $params = ['userid' => $userid];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        $sql = "SELECT p.userid
                  FROM {paygw_paypal} pgp
                  JOIN {payments} p ON pgp.paymentid = p.id
                  JOIN {payment_accounts} pa ON p.accountid = pa.id
                 WHERE pa.contextid = :contextid";

        $params = ['contextid' => $context->id];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT pgp.pp_orderid, pa.contextid
                  FROM {paygw_paypal} pgp
                  JOIN {payments} p ON pgp.paymentid = p.id
                  JOIN {payment_accounts} pa ON p.accountid = pa.id
                 WHERE p.userid = :userid AND pa.contextid $contextsql
              ORDER BY pa.contextid";
        $params = ['userid' => $user->id] + $contextparams;

        // Reference to the context seen in the last iteration of the loop. By comparing this with the current record, and
        // because we know the results are ordered, we know when we've moved to the payments for a new context and therefore
        // when we can export the complete data for the last context.
        $lastcontextid = null;

        $subcontext = [
            get_string('payments', 'payment'),
            'paypal',
        ];
        $contextorders = [];

        $orders = $DB->get_recordset_sql($sql, $params);
        foreach ($orders as $order) {
            // If we've moved to a new context, then write the last context data and reinitialise the transactions array.
            if ($lastcontextid != $order->contextid) {
                if (!empty($contextorders)) {
                    $context = \context::instance_by_id($lastcontextid);
                    writer::with_context($context)->export_data(
                        $subcontext,
                        (object) ['orders' => $contextorders]
                    );
                }
                $contextorders = [];
            }

            $contextorders[] = (object) [
                'pp_orderid' => $order->pp_orderid,
            ];

            $lastcontextid = $order->contextid;
        }
        $orders->close();

        // The data for the last context won't have been written yet, so make sure to write it now!
        if (!empty($contextorders)) {
            $context = \context::instance_by_id($lastcontextid);
            writer::with_context($context)->export_data(
                $subcontext,
                (object) ['pp_orderid' => $contextorders]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        $select = "paymentid IN (
                       SELECT p.id
                         FROM {payments} p
                         JOIN {payment_accounts} pa ON p.accountid = pa.id
                        WHERE pa.contextid = :contextid
                   )";
        $params = ['contextid' => $context->id];
        $DB->delete_records_select('paygw_paypal', $select, $params);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $contextids = $contextlist->get_contextids();

        if (!$contextids) {
            return;
        }

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $select = "paymentid IN (
                       SELECT p.id
                         FROM {payments} p
                         JOIN {payment_accounts} pa ON p.accountid = pa.id
                        WHERE p.userid = :userid AND pa.contextid $contextsql
                   )";
        $params = ['userid' => $userid] + $contextparams;

        $DB->delete_records_select('payments', $select, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist   $userlist   The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $select = "paymentid IN (
                       SELECT p.id
                         FROM {payments} p
                         JOIN {payment_accounts} pa ON p.accountid = pa.id
                        WHERE p.userid $usersql AND pa.contextid = :contextid
                   )";
        $params = ['contextid' => $context->id] + $userparams;

        $DB->delete_records_select('payments', $select, $params);
    }
}
