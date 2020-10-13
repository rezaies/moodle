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
 * Privacy Subsystem implementation for core_payment.
 *
 * @package    core_payment
 * @category   privacy
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_payment\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for core_payment.
 *
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This component has data.
    // We need to return all payment information where the user is
    // listed in the payment.userid field.
    // We may also need to fetch this informtion from individual plugins in some cases.
    // e.g. to fetch the full and other gateway-specific meta-data.
    \core_privacy\local\metadata\provider,

    // This is a subsysytem which provides information to core.
    \core_privacy\local\request\subsystem\provider,

    // This is a subsysytem which provides information to plugins.
    \core_privacy\local\request\subsystem\plugin_provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin is capable of determining which users have data within it for the plugins it provides data to.
    \core_privacy\local\request\shared_userlist_provider
{

    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // The 'payments' table contains data about payments.
        $collection->add_database_table('payments', [
            'userid'            => 'privacy:metadata:database:payments:userid',
            'amount'            => 'privacy:metadata:database:payments:amount',
            'currency'          => 'privacy:metadata:database:payments:currency',
            'gateway'           => 'privacy:metadata:database:payments:gateway',
            'timecreated'       => 'privacy:metadata:database:payments:timecreated',
            'timemodified'      => 'privacy:metadata:database:payments:timemodified',
        ], 'privacy:metadata:database:payments');

        return $collection;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   int $userid The user to search.
     * @return  contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT pa.contextid
                  FROM {payment_accounts} pa
                  JOIN {payment_gateways} pg ON pg.accountid = pa.id
                  JOIN {payments} p ON (p.accountid = pg.accountid AND p.gateway = pg.gateway)
                 WHERE p.userid = :userid";

        $params = ['userid' => $userid];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        $sql = "SELECT p.userid
                  FROM {payments} p
                  JOIN {payment_gateways} pg ON (p.accountid = pg.accountid AND p.gateway = pg.gateway)
                  JOIN {payment_accounts} pa ON pg.accountid = pa.id
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

        $sql = "SELECT p.*, pa.contextid
                  FROM {payments} p
                  JOIN {payment_gateways} pg ON (p.accountid = pg.accountid AND p.gateway = pg.gateway)
                  JOIN {payment_accounts} pa ON pg.accountid = pa.id
                 WHERE p.userid = :userid AND pa.contextid $contextsql
              ORDER BY pa.contextid";
        $params = ['userid' => $user->id] + $contextparams;

        // Reference to the context seen in the last iteration of the loop. By comparing this with the current record, and
        // because we know the results are ordered, we know when we've moved to the payments for a new context and therefore
        // when we can export the complete data for the last context.
        $lastcontextid = null;

        $strpayments = get_string('payments', 'payment');
        $transactions = [];

        $payments = $DB->get_recordset_sql($sql, $params);
        foreach ($payments as $payment) {
            // If we've moved to a new context, then write the last context data and reinitialise the transactions array.
            if ($lastcontextid != $payment->contextid) {
                if (!empty($transactions)) {
                    $context = \context::instance_by_id($lastcontextid);
                    writer::with_context($context)->export_data(
                        [$strpayments],
                        (object) ['transactions' => $transactions]
                    );
                }
                $transactions = [];
            }

            $lastcontextid = $payment->contextid;
            unset($payment->contextid);
            $transactions[] = $payment;
        }
        $payments->close();

        // The data for the last context won't have been written yet, so make sure to write it now!
        if (!empty($transactions)) {
            $context = \context::instance_by_id($lastcontextid);
            writer::with_context($context)->export_data(
                [$strpayments],
                (object) ['transactions' => $transactions]
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

        $select = "accountid IN (SELECT pa.id FROM {payment_accounts} pa WHERE pa.contextid = :contextid)";
        $params = ['contextid' => $context->id];
        $DB->delete_records_select('payments', $select, $params);
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
        $select = "userid = :userid AND accountid IN (SELECT pa.id FROM {payment_accounts} pa WHERE pa.contextid $contextsql)";
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

        $select = "userid $usersql AND accountid IN (SELECT pa.id FROM {payment_accounts} pa WHERE pa.contextid = :contextid)";
        $params = ['contextid' => $context->id] + $userparams;

        $DB->delete_records_select('payments', $select, $params);
    }
}
