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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Provides the information to backup gradebookservices lineitems
 *
 * @package    ltixservice_gradebookservices
 * @category   backup
 * @copyright  2025 Shamim Rezaie <shamim@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ltixservice_gradebookservices_plugin extends backup_plugin {
    /** @var int TypeId contained in DB but is invalid */
    const NONVALIDTYPEID = 0;

    /**
     * Returns the plugin information to attach to submission element
     *
     * @return backup_plugin_element
     */
    protected function define_ltiresourcelink_plugin_structure(): backup_plugin_element {
        global $DB;

        // Create XML elements.
        $plugin = $this->get_plugin_element();
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        // The gbs entries related to this element.
        $lineitems = new backup_nested_element('lineitems');
        $lineitem = new backup_nested_element('lineitem', ['id'], [
            'gradeitemid',
            'courseid',
            'toolproxyid',
            'typeid',
            'baseurl',
            'ltilinkid',
            'resourceid',
            'tag',
            'subreviewurl',
            'subreviewparams',
            'vendorcode',
            'guid',
        ]);

        // Build the tree.
        $plugin->add_child($pluginwrapper);
        $pluginwrapper->add_child($lineitems);
        $lineitems->add_child($lineitem);

        // Define sources.
        $courseid = $this->task->get_courseid();
        $contextid = $this->task->get_contextid();
        $resourcelinks = $DB->get_records('lti_resource_link', ['contextid' => $contextid]);
        $sourcearray = [];
        foreach ($resourcelinks as $resourcelink) {
            // We need to know the actual activity tool or toolproxy.
            // If and activity is assigned to a type that doesn't exist, we don't want to backup any related lineitems.``
            // Default to invalid condition.
            $typeid = 0;
            $toolproxyid = '0';

            $ltitype = $DB->get_record('lti_types', ['id' => $resourcelink->typeid], 'toolproxyid, baseurl');
            if ($ltitype) {
                $typeid = $resourcelink->typeid;
                $toolproxyid = $ltitype->toolproxyid;
            } else if ($resourcelink->typeid == self::NONVALIDTYPEID) { // This activity comes from an old backup.
                // 1. Let's check if the activity is coupled. If so, find the values in the GBS element.
                // TODO: In the following code, I assumed that ltilinkid is repurposed to refer to `lti_resourcelink.id`.
                $gbsrecord = $DB->get_record(
                    'ltixservice_gradebookservices',
                    ['ltilinkid' => $resourcelink->id],
                    'typeid, toolproxyid, baseurl'
                );
                if ($gbsrecord) {
                    $typeid = $gbsrecord->typeid;
                    $toolproxyid = $gbsrecord->toolproxyid;
                } else { // 2. If it is uncoupled... we will need to guess the right activity typeid
                    // Guess the typeid for the activity.
                    $tool = \core_ltix\helper::get_tool_by_url_match($resourcelink->url, $courseid);
                    if ($tool) {
                        $alttypeid = $tool->id;
                        // If we have a valid typeid then get types again.
                        if ($alttypeid != self::NONVALIDTYPEID) {
                            $ltitype = $DB->get_record('lti_types', ['id' => $alttypeid], 'toolproxyid, baseurl');
                            $toolproxyid = $ltitype->toolproxyid;
                        }
                    }
                }
            }

            if ($toolproxyid != null) {
                $lineitemssql = "SELECT l.*, t.vendorcode as vendorcode, t.guid as guid
                                   FROM {ltixservice_gradebookservices} l
                             INNER JOIN {lti_tool_proxies} t ON (t.id = l.toolproxyid)
                                  WHERE l.courseid = ?
                                    AND l.toolproxyid = ?
                                    AND l.typeid is null";
                $lineitemsparams = ['courseid' => $courseid, $toolproxyid];
            } else {
                $lineitemssql = "SELECT l.*, null as vendorcode, null as guid
                                   FROM {ltixservice_gradebookservices} l
                                  WHERE l.courseid = ?
                                    AND l.typeid = ?
                                    AND l.toolproxyid is null";
                $lineitemsparams = ['courseid' => $courseid, $typeid];
            }
            $sourcearray += $DB->get_records_sql($lineitemssql, $lineitemsparams);
        }

        $lineitem->set_source_array($sourcearray);

        return $plugin;
    }
}
