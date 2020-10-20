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
 * Payment subsystem callback implementation for enrol_fee.
 *
 * @package    enrol_fee
 * @category   payment
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_fee\payment;

/**
 * Payment subsystem callback implementation for enrol_fee.
 *
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_payment\local\callback\provider {

    /**
     * Callback function that returns the enrolment cost for the course that $instanceid enrolment instance belongs to.
     *
     * @param int $instanceid The enrolment instance id
     * @return array['amount' => float, 'currency' => string]
     */
    public static function get_cost(int $instanceid): array {
        global $DB;

        $instance = $DB->get_record('enrol', ['enrol' => 'fee', 'id' => $instanceid], '*', MUST_EXIST);

        return [
            'amount' => (float) $instance->cost,
            'currency' => $instance->currency,
        ];
    }

    /**
     * Callback function that delivers what the user paid for to them.
     *
     * @param int $instanceid The enrolment instance id
     * @return bool Whether successful or not
     */
    public static function deliver_order(int $instanceid): bool {
        global $DB, $USER;

        $instance = $DB->get_record('enrol', ['enrol' => 'fee', 'id' => $instanceid], '*', MUST_EXIST);

        $plugin = enrol_get_plugin('fee');

        if ($instance->enrolperiod) {
            $timestart = time();
            $timeend   = $timestart + $instance->enrolperiod;
        } else {
            $timestart = 0;
            $timeend   = 0;
        }

        $plugin->enrol_user($instance, $USER->id, $instance->roleid, $timestart, $timeend);

        return true;
    }
}
