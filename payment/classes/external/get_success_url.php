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

namespace core_payment\external;

use core_payment\helper;
use external_api;
use external_function_parameters;
use external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * This is the external method for returning the url of the page the user should be redirected to after a successful payment.
 *
 * @package    core_payment
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_success_url extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component')
        ]);
    }

    /**
     * Returns the URL of the page the user should be redirected to after a successful payment.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return string
     */
    public static function execute(string $component, string $paymentarea, int $itemid): string {

        $params = external_api::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);

        return helper::get_success_url($params['component'], $params['paymentarea'], $params['itemid'])->out(false);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_URL, 'Success page URL');
    }
}
