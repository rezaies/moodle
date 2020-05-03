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
 * This class contains a list of webservice functions related to feedback CTA.
 *
 * @package    core
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\local\cta\feedback;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;

/**
 * Class external
 *
 * The external API for feedback CTA.
 *
 * @package core\local\cta\feedback
 */
class external extends external_api {

    public static function get_feedback_data_parameters() {
        return new external_function_parameters(
                [
                    'contextid' => new external_value(PARAM_INT, 'The context id of the page the user is in'),
                ]
        );
    }

    /**
     * Prepare and return all the data we need before going to the feedback site
     *
     * @param int $contextid The context id
     * @return \stdClass
     */
    public static function get_feedback_data(int $contextid) {
        global $CFG, $USER, $PAGE;

        require_once($CFG->libdir . '/adminlib.php');

        $context = \context::instance_by_id($contextid);
        self::validate_context($context);
        $PAGE->set_context($context);

        $result = new \stdClass();
        $result->lang = $USER->lang;
        $result->siteurl = $CFG->wwwroot;
        $result->roles = self::get_all_user_roles();
        $result->version = $CFG->release;
        $result->theme = $PAGE->theme->name;
        $result->themeversion = get_component_version('theme_' . $result->theme);

        return $result;

    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function get_feedback_data_returns() {
        return new external_single_structure(
                [
                    'lang' => new external_value(PARAM_ALPHA, 'User\'s preferred language'),
                    'siteurl' => new external_value(PARAM_TEXT, 'Moodle\'s URL'),
                    'roles' => new external_multiple_structure(new external_value(PARAM_TEXT, 'role shortname')),
                    'version' => new external_value(PARAM_TEXT, 'Possible answers and info.'),
                    'theme' => new external_value(PARAM_PLUGIN, 'Theme'),
                    'themeversion' => new external_value(PARAM_TEXT, 'Theme version'),
                ]
        );
    }

    /**
     * returns all the roles that the current user has at different places.
     *
     * @return string[]
     */
    private static function get_all_user_roles(): array {
        global $DB, $USER;

        $sql = "SELECT DISTINCT r.shortname
                  FROM {role} r
                  JOIN {role_assignments} ra ON ra.roleid = r.id
                 WHERE ra.userid = ?";
        $roles = $DB->get_fieldset_sql($sql, [$USER->id]);
        if (is_siteadmin($USER)) {
            $roles[] = 'admin';
        }

        return $roles;
    }

    /**
     * Returns description of record_action() parameters.
     *
     * @return external_function_parameters
     */
    public static function record_action_parameters() {
        return new external_function_parameters(
                [
                    'action' => new external_value(PARAM_ALPHA, 'The action taken by user'),
                ]
        );
    }

    /**
     * Record users action to the feedback CTA
     *
     * @param string $action The action the user took
     * @throws \invalid_parameter_exception
     */
    public static function record_action(string $action) {
        external_api::validate_parameters(self::record_action_parameters(), ['action' => $action]);

        switch ($action) {
            case 'give':
                set_user_preference('core_cta_feedback_give', time());
                break;
            case 'remind':
                set_user_preference('core_cta_feedback_remind', time());
                break;
            default:
                throw new \invalid_parameter_exception('Invalid value for action parameter (value: ' . $action . '),' .
                        'allowed values are: give,remind');
        }
    }

    /**
     * Returns description of method result value
     *
     * @return null
     */
    public static function record_action_returns() {
        return null;
    }
}
