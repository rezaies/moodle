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
 * External API to return data needed by the core/userfeedback JS module.
 *
 * @package    core
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\external\userfeedback;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;

/**
 * The external API to get all data needed by the core/userfeesback JS module.
 *
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_data extends external_api {
    /**
     * Returns description of parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id of the page the user is in'),
        ]);
    }

    /**
     * Prepare and return all the data we need before going to the feedback site
     *
     * @param int $contextid The context id
     * @return \stdClass
     */
    public static function execute(int $contextid) {
        global $CFG, $PAGE;

        require_once($CFG->libdir . '/adminlib.php');

        external_api::validate_parameters(self::execute_parameters(), ['contextid' => $contextid]);

        $context = \context::instance_by_id($contextid);
        self::validate_context($context);
        $PAGE->set_context($context);

        $result = new \stdClass();
        $result->lang = clean_param(current_language(), PARAM_LANG); // Avoid breaking WS because of incorrect package langs.
        $result->siteurl = $CFG->wwwroot;
        $result->feedbackurl = $CFG->userfeedback_url ?? 'https://feedback.moodle.org/lms';
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
    public static function execute_returns() {
        return new external_single_structure([
            'lang' => new external_value(PARAM_LANG, 'User\'s preferred language'),
            'siteurl' => new external_value(PARAM_URL, 'Moodle\'s URL'),
            'feedbackurl' => new external_value(PARAM_URL, 'Feedback site\'s URL'),
            'roles' => new external_multiple_structure(new external_value(PARAM_TEXT, 'role shortname')),
            'version' => new external_value(PARAM_TEXT, 'Possible answers and info.'),
            'theme' => new external_value(PARAM_PLUGIN, 'Theme'),
            'themeversion' => new external_value(PARAM_TEXT, 'Theme version'),
        ]);
    }

    /**
     * Returns all the roles that the current user has at different places.
     * This function is placed here rather that in accesslib.php because the logic used here is very custom
     * to the userfeedback feature. This functions adds 'admin' to the roles list if the user is a site admin.
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
}
