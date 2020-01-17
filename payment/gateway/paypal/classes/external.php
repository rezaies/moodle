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
 * This class contains a list of webservice functions related to the PayPal payment gateway.
 *
 * @package    pg_paypal
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace pg_paypal;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class external extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_config_for_js_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns the full URL of the PayPal JavaScript SDK.
     *
     * @return string[]
     */
    public static function get_config_for_js(): array {
        $config = get_config('pg_paypal');

        return [
            'clientid' => $config->clientid,
            'brandname' => $config->brandname,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function get_config_for_js_returns(): external_single_structure {
        return new external_single_structure([
                'clientid' => new external_value(PARAM_TEXT, 'PayPal client ID'),
                'brandname' => new external_value(PARAM_TEXT, 'Brand name'),
        ]);
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_sdk_url_parameters(): external_function_parameters {
        return new external_function_parameters([
            'currency' => new external_value(PARAM_ALPHA, 'Payment currency code.', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Returns the full URL of the PayPal JavaScript SDK.
     *
     * @param string $currency
     * @return string
     */
    public static function get_sdk_url(string $currency = ''): string {
        ['currency' => $currency] = self::validate_parameters($currency);

        $clientid = get_config('pg_paypal', 'clientid');

        $url = "https://www.paypal.com/sdk/js?client-id=$clientid";

        if ($currency) {
            $url .= "&currency=$currency";
        }

        return $url;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_value
     */
    public static function get_sdk_url_returns(): external_value {
        return new external_value(PARAM_URL, 'PayPal JavaScript SDK\'s URL');
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function transaction_complete_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'The component name'),
            'componentid' => new external_value(PARAM_INT, 'The item id in the context of the component'),
            'orderid' => new external_value(PARAM_TEXT, 'The order id coming back from PayPal'),
        ]);
    }

    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the componentid belongs to
     * @param int $componentid An internal identifier that is used by the component
     * @param string $orderid PayPal order ID
     * @return array
     */
    public static function transaction_complete(string $component, int $componentid, string $orderid): array {
        global $USER, $DB;

        self::validate_parameters(self::transaction_complete_parameters(), [
            'component' => $component,
            'componentid' => $componentid,
            'orderid' => $orderid,
        ]);

        $config = get_config('pg_paypal');

        $sandbox = $config->environment == 'sandbox';

        [
            'amount' => $amount,
            'currency' => $currency
        ] = \core_payment\helper::get_cost($component, $componentid);

        $paypalhelper = new paypal_helper($config->clientid, $config->secret, $sandbox);
        $orderdetails = $paypalhelper->get_order_details($orderid);

        $success = false;
        $message = '';

        if ($orderdetails) {
            if ($orderdetails['status'] == 'APPROVED' && $orderdetails['intent'] == 'CAPTURE') {
                $item = $orderdetails['purchase_units'][0];
                if ($item['amount']['value'] == $amount && $item['amount']['currency_code'] == $currency) {
                    $capture = $paypalhelper->capture_order($orderid);
                    if ($capture && $capture['status'] == 'COMPLETED') {
                        $success = true;
                        // Everything is correct. Let's give them what they paid for.
                        try {
                            \core_payment\helper::deliver_order($component, $componentid);

                            $paymentid = \core_payment\helper::save_payment($component, $componentid, (int)$USER->id, $amount,
                                    $currency, 'paypal');

                            // Store PayPal extra information.
                            $record = new \stdClass();
                            $record->paymentid = $paymentid;
                            $record->pp_orderid = $orderid;
                            $record->pp_status = 'COMPLETED';

                            $DB->insert_record('pg_paypal', $record);
                        } catch (\Exception $e) {
                            debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                            $success = false;
                            $message = get_string('internalerror', 'pg_paypal');
                        }
                    } else {
                        $success = false;
                        $message = get_string('paymentnotcleared', 'pg_paypal');
                    }
                } else {
                    $success = false;
                    $message = get_string('amountmismatch', 'pg_paypal');
                }
            } else {
                $success = false;
                $message = get_string('paymentnotcleared', 'pg_paypal');
            }
        } else {
            // Could not capture authorization!
            $success = false;
            $message = get_string('cannotfetchorderdatails', 'pg_paypal');
        }

        return [
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function transaction_complete_returns() {
        return new external_function_parameters([
            'success' => new external_value(PARAM_BOOL, 'Whether everything was successful or not.'),
            'message' => new external_value(PARAM_TEXT, 'Message (usually the error message).', VALUE_OPTIONAL),
        ]);
    }
}
