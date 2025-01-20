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
 * Restore plugin class.
 *
 * Provides the necessary information
 * needed to restore the lineitems related to an ltix tool (coupled),
 * and all the uncoupled ones from the course.
 *
 * @package    ltixservice_gradebookservices
 * @category   backup
 * @copyright  2025 Shamim Rezaie <shamim@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ltixservice_gradebookservices_plugin extends restore_plugin {
    /**
     * Returns the plugin structure to attach to the XML element.
     *
     * @return restore_path_element[] array of elements to be processed on restore.
     */
    protected function define_ltiresourcelink_plugin_structure(): array {
        $paths = [];
        $elename = $this->get_namefor('lineitem');
        $elepath = $this->get_pathfor('/lineitems/lineitem');
        $paths[] = new restore_path_element($elename, $elepath);
        return $paths;
    }

    /**
     * Processes one lineitem
     *
     * @param array $data The lineitem data to restore.
     * @return void
     */
    public function process_ltixservice_gradebookservices_lineitem(array $data): void {
        global $DB;
        $data = (object) $data;
        // The coupled lineitems are restored as any other grade item
        // so we will only create the entry in the ltixservice_gradebookservices table.
        // We will try to find a valid toolproxy in the system.
        // If it has been found before... we use it.

        $courseid = $this->task->get_courseid();
        if ($data->typeid != null) {
            if ($ltitypeid = $this->get_mappingid('ltitype', $data->typeid)) {
                $newtypeid = $ltitypeid;
            } else { // If not, then we will call our own function to find it.
                $newtypeid = $this->find_typeid($data, $courseid);
            }
        } else {
            $newtypeid = null;
        }
        if ($data->toolproxyid != null) {
            $ltitoolproxy = $this->get_mappingid('ltitoolproxy', $data->toolproxyid);
            if ($ltitoolproxy && $ltitoolproxy != 0) {
                $newtoolproxyid = $ltitoolproxy;
            } else { // If not, then we will call our own function to find it.
                $newtoolproxyid = $this->find_proxy_id($data);
            }
        } else {
            $newtoolproxyid = null;
        }
        // TODO: In the following code, I assumed that ltilinkid is repurposed to refer to `lti_resourcelink.id`.
        if ($data->ltilinkid != null) {
            if ($data->ltilinkid != $this->get_old_parentid('ltiresourcelink')) {
                // This is a linked item, but not for the current lti link, so skip it.
                return;
            }
            $ltilinkid = $this->get_new_parentid('ltiresourcelink');
        } else {
            $ltilinkid = null;
        }
        $resourceid = null;
        if (property_exists($data, 'resourceid')) {
            $resourceid = $data->resourceid;
        }
        // If this has not been restored before.
        if ($this->get_mappingid('gbsgradeitemrestored', $data->id, 0) == 0) {
            $newgbsid = $DB->insert_record('ltixservice_gradebookservices', (object) [
                'gradeitemid' => 0,
                'courseid' => $courseid,
                'toolproxyid' => $newtoolproxyid,
                'ltilinkid' => $ltilinkid,
                'typeid' => $newtypeid,
                'baseurl' => $data->baseurl,
                'resourceid' => $resourceid,
                'tag' => $data->tag,
                'subreviewparams' => $data->subreviewparams ?? '',
                'subreviewurl' => $data->subreviewurl ?? '',
            ]);
            $this->set_mapping('gbsgradeitemoldid', $newgbsid, $data->gradeitemid);
            $this->set_mapping('gbsgradeitemrestored', $data->id, $data->id);
        }
    }

    /**
     * If the toolproxy is not in the mapping (or it is 0)
     * we try to find the toolproxyid.
     * If none is found, then we set it to 0.
     *
     * @param stdClass $data An object containing the `guid` and `vendorcode` used to locate the proxy ID.
     * @return int The proxy ID if found, or 0 if no matching proxy ID exists.
     */
    private function find_proxy_id(stdClass $data): int {
        global $DB;
        $newtoolproxyid = 0;
        $oldtoolproxyguid = $data->guid;
        $oldtoolproxyvendor = $data->vendorcode;

        $dbtoolproxyjsonparams = ['guid' => $oldtoolproxyguid, 'vendorcode' => $oldtoolproxyvendor];
        $dbtoolproxy = $DB->get_field('lti_tool_proxies', 'id', $dbtoolproxyjsonparams);
        if ($dbtoolproxy) {
            $newtoolproxyid = $dbtoolproxy;
        }
        return $newtoolproxyid;
    }

    /**
     * If the typeid is not in the mapping or it is 0, (it should be most of the time)
     * we will try to find the better typeid that matches with the lineitem.
     * If none is found, then we set it to 0.
     *
     * @param stdClass $data The data object containing type information, including 'typeid' and 'baseurl'.
     * @param int $courseid The ID of the course to search within.
     * @return int The new-found LTI type ID or 0 if no matching type is found.
     */
    private function find_typeid(stdClass $data, int $courseid): int {
        global $DB;
        $newtypeid = 0;
        $oldtypeid = $data->typeid;

        // 1. Find a type with the same id in the same course.
        $dbtypeidparameter = [
            'id' => $oldtypeid,
            'course' => $courseid,
            'baseurl' => $data->baseurl,
        ];
        $dbtype = $DB->get_field_select(
            'lti_types',
            'id',
            "id=:id AND course=:course AND " . $DB->sql_compare_text('baseurl') . "=:baseurl",
            $dbtypeidparameter
        );
        if ($dbtype) {
            $newtypeid = $dbtype;
        } else {
            // 2. Find a site type for all the courses (course == 1), but with the same id.
            $dbtypeidparameter = ['id' => $oldtypeid, 'baseurl' => $data->baseurl];
            $dbtype = $DB->get_field_select(
                'lti_types',
                'id',
                "id=:id AND course=1 AND " . $DB->sql_compare_text('baseurl') . "=:baseurl",
                $dbtypeidparameter
            );
            if ($dbtype) {
                $newtypeid = $dbtype;
            } else {
                // 3. Find a type with the same baseurl in the actual site.
                $dbtypeidparameter = ['course' => $courseid, 'baseurl' => $data->baseurl];
                $dbtype = $DB->get_field_select(
                    'lti_types',
                    'id',
                    "course=:course AND " . $DB->sql_compare_text('baseurl') . "=:baseurl",
                    $dbtypeidparameter
                );
                if ($dbtype) {
                    $newtypeid = $dbtype;
                } else {
                    // 4. Find a site type for all the courses (course == 1) with the same baseurl.
                    $dbtypeidparameter = ['course' => 1, 'baseurl' => $data->baseurl];
                    $dbtype = $DB->get_field_select(
                        'lti_types',
                        'id',
                        "course=1 AND " . $DB->sql_compare_text('baseurl') . "=:baseurl",
                        $dbtypeidparameter
                    );
                    if ($dbtype) {
                        $newtypeid = $dbtype;
                    }
                }
            }
        }
        return $newtypeid;
    }

    /**
     * We call the after_restore_ltiresourcelink to update the grade_items id's that we didn't know in the moment of creating
     * the gradebookservices rows.
     */
    protected function after_restore_ltiresourcelink(): void {
        global $DB;
        $courseid = $this->task->get_courseid();
        $gbstoupdate = $DB->get_records('ltixservice_gradebookservices', ['gradeitemid' => 0, 'courseid' => $courseid]);
        foreach ($gbstoupdate as $gbs) {
            $oldgradeitemid = $this->get_mappingid('gbsgradeitemoldid', $gbs->id, 0);
            $newgradeitemid = $this->get_mappingid('grade_item', $oldgradeitemid, 0);
            if ($newgradeitemid > 0) {
                $gbs->gradeitemid = $newgradeitemid;
                if (!isset($gbs->resourceid)) {
                    // Before 3.9 resourceid was stored in grade_item->idnumber.
                    $gbs->resourceid = $DB->get_field('grade_items', 'idnumber', ['id' => $newgradeitemid]);
                }
                $DB->update_record('ltixservice_gradebookservices', $gbs);
            }
        }
        // Pre 3.9 backups did not include a gradebookservices record. Adding one here if missing for the restored instance.
        // TODO: This is incomplete, we should also check if the lti_resourcelink has a gradebookservices record.
    }
}
