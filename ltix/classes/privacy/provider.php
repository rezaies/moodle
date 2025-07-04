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

namespace core_ltix\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy Subsystem for core_ltix implementing null_provider.
 *
 * @package    core_ltix
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // core_ltix stores user data.
    \core_privacy\local\metadata\provider,

    // The core_ltix subsystem provides data to other components.
    \core_privacy\local\request\subsystem\plugin_provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // The core_ltix subsystem may have data that belongs to this user.
    \core_privacy\local\request\plugin\provider,

    \core_privacy\local\request\shared_userlist_provider
{
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'lti_provider',
            [
                'userid' => 'privacy:metadata:userid',
                'username' => 'privacy:metadata:username',
                'useridnumber' => 'privacy:metadata:useridnumber',
                'firstname' => 'privacy:metadata:firstname',
                'lastname' => 'privacy:metadata:lastname',
                'fullname' => 'privacy:metadata:fullname',
                'email' => 'privacy:metadata:email',
                'role' => 'privacy:metadata:role',
                'courseid' => 'privacy:metadata:courseid',
                'courseidnumber' => 'privacy:metadata:courseidnumber',
                'courseshortname' => 'privacy:metadata:courseshortname',
                'coursefullname' => 'privacy:metadata:coursefullname',
            ],
            'privacy:metadata:externalpurpose'
        );

        $collection->add_database_table(
            'lti_submission',
            [
                'userid' => 'privacy:metadata:lti_submission:userid',
                'datesubmitted' => 'privacy:metadata:lti_submission:datesubmitted',
                'dateupdated' => 'privacy:metadata:lti_submission:dateupdated',
                'gradepercent' => 'privacy:metadata:lti_submission:gradepercent',
                'originalgrade' => 'privacy:metadata:lti_submission:originalgrade',
            ],
            'privacy:metadata:lti_submission'
        );

        $collection->add_database_table(
            'lti_tool_proxies',
            [
                'name' => 'privacy:metadata:lti_tool_proxies:name',
                'createdby' => 'privacy:metadata:createdby',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:lti_tool_proxies'
        );
        $collection->add_database_table(
            'lti_types',
            [
                'name' => 'privacy:metadata:lti_types:name',
                'createdby' => 'privacy:metadata:createdby',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:lti_types'
        );
        return $collection;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        // TODO MDL-85891: In the following code, I assumed that ltiid is repurposed to refer to `lti_resourcelink.id`.
        $sql = "SELECT s.userid
                  FROM {lti_submission} s
                  JOIN {lti_resource_link} rl ON rl.id = s.ltiid
                 WHERE rl.contextid = :contextid";
        $params = ['contextid' => $context->id];
        $userlist->add_from_sql('userid', $sql, $params);

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            // Fetch all LTI tool proxies.
            $sql = "SELECT ltp.createdby AS userid
                      FROM {lti_tool_proxies} ltp";
            $userlist->add_from_sql('userid', $sql, []);
        }

        if ($context->contextlevel == CONTEXT_COURSE) {
            // Fetch all LTI types.
            $sql = "SELECT lt.createdby AS userid
                 FROM {context} c
                 JOIN {course} course
                   ON c.contextlevel = :contextlevel
                  AND c.instanceid = course.id
                 JOIN {lti_types} lt
                   ON lt.course = course.id
                WHERE c.id = :contextid";
            $params = [
                'contextlevel' => CONTEXT_COURSE,
                'contextid' => $context->id,
            ];
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // TODO MDL-85891: In the following code, I assumed that ltiid is repurposed to refer to `lti_resourcelink.id`.
        $sql = "SELECT rl.contextid
                  FROM {lti_submission} s
                  JOIN {lti_resource_link} rl ON rl.id = s.ltiid
                 WHERE s.userid = :userid";
        $params = ['userid' => $userid];
        $contextlist->add_from_sql($sql, $params);

        // Fetch all LTI types.
        $sql = "SELECT c.id
                 FROM {context} c
                 JOIN {course} course ON c.contextlevel = :contextlevel AND c.instanceid = course.id
                 JOIN {lti_types} ltit ON ltit.course = course.id
                WHERE ltit.createdby = :userid";
        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        // The LTI tool proxies sit in the system context.
        $contextlist->add_system_context();
        return $contextlist;
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        self::export_user_data_lti_submissions($contextlist);
        self::export_user_data_lti_types($contextlist);
        self::export_user_data_lti_tool_proxies($contextlist);
    }

    /**
     * Export personal data for the given approved_contextlist related to LTI types.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     * @return void
     */
    protected static function export_user_data_lti_types(approved_contextlist $contextlist): void {
        global $DB;

        // Filter out any contexts that are not related to courses.
        $courseids = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $carry[] = $context->instanceid;
            }
            return $carry;
        }, []);

        if (empty($courseids)) {
            return;
        }

        $user = $contextlist->get_user();

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['userid' => $user->id]);
        $ltitypes = $DB->get_recordset_select('lti_types', "course $insql AND createdby = :userid", $params, 'timecreated ASC');
        self::recordset_loop_and_export($ltitypes, 'course', [], function ($carry, $record) {
            $context = \context_course::instance($record->course);
            $options = ['context' => $context];
            $carry[] = [
                'name' => format_string($record->name, true, $options),
                'createdby' => transform::user($record->createdby),
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
            ];
            return $carry;
        }, function ($courseid, $data) {
            $context = \context_course::instance($courseid);
            $finaldata = (object) ['lti_types' => $data];
            writer::with_context($context)->export_data([], $finaldata);
        });
    }

    /**
     * Export personal data for the given approved_contextlist related to LTI tool proxies.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     * @return void
     */
    protected static function export_user_data_lti_tool_proxies(approved_contextlist $contextlist): void {
        global $DB;

        // Filter out any contexts that are not related to system context.
        $systemcontexts = array_filter($contextlist->get_contexts(), function ($context) {
            return $context->contextlevel == CONTEXT_SYSTEM;
        });

        if (empty($systemcontexts)) {
            return;
        }

        $user = $contextlist->get_user();

        $systemcontext = \context_system::instance();

        $data = [];
        $ltiproxies = $DB->get_recordset('lti_tool_proxies', ['createdby' => $user->id], 'timecreated ASC');
        foreach ($ltiproxies as $ltiproxy) {
            $data[] = [
                'name' => format_string($ltiproxy->name, true, ['context' => $systemcontext]),
                'createdby' => transform::user($ltiproxy->createdby),
                'timecreated' => transform::datetime($ltiproxy->timecreated),
                'timemodified' => transform::datetime($ltiproxy->timemodified),
            ];
        }
        $ltiproxies->close();

        $finaldata = (object) ['lti_tool_proxies' => $data];
        writer::with_context($systemcontext)->export_data([], $finaldata);
    }

    /**
     * Export personal data for the given approved_contextlist related to LTI submissions.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     * @return void
     */
    protected static function export_user_data_lti_submissions(approved_contextlist $contextlist): void {
        global $DB;

        $contextids = array_column($contextlist->get_contexts(), 'id');
        if (empty($contextids)) {
            return;
        }

        $user = $contextlist->get_user();

        // TODO MDL-85891: In the following code, I assumed that ltiid is repurposed to refer to `lti_resourcelink.id`.
        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        // Get all the LTI resource kinks associated with the above contexts.
        $linkidstocontextids = $DB->get_records_sql_menu(
            "SELECT rl.id, rl.contextid
               FROM {lti_resource_link} rl
              WHERE rl.contextid $insql",
            $inparams
        );
        $recordset = $DB->get_recordset_sql(
            "SELECT s.ltiid, s.datesubmitted, s.dateupdated, s.gradepercent, s.originalgrade, rl.contextid
               FROM {lti_submission} s
               JOIN {lti_resource_link} rl ON rl.id = s.ltiid
              WHERE rl.contextid $insql AND s.userid = :userid",
            array_merge($inparams, ['userid' => $user->id]),
            'dateupdated, ltiid'
        );
        self::recordset_loop_and_export(
            $recordset,
            'ltiid',
            [],
            function ($carry, $record) {
                $carry[] = [
                    'gradepercent' => $record->gradepercent,
                    'originalgrade' => $record->originalgrade,
                    'datesubmitted' => transform::datetime($record->datesubmitted),
                    'dateupdated' => transform::datetime($record->dateupdated),
                ];
                return $carry;
            },
            function ($ltiid, $data) use ($user, $linkidstocontextids) {
                $context = \context::instance_by_id($linkidstocontextids[$ltiid]);
                $contextdata = helper::get_context_data($context, $user);
                $finaldata = (object) array_merge((array) $contextdata, ['submissions' => $data]);
                helper::export_context_files($context, $user);
                writer::with_context($context)->export_data([], $finaldata);
            }
        );
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // TODO MDL-85891: In the following code, I assumed that ltiid is repurposed to refer to `lti_resourcelink.id`.
        $DB->delete_records_subquery(
            'lti_submission',
            'ltiid',
            'id',
            'SELECT id FROM {lti_resource_link} WHERE contextid = :contextid',
            ['contextid' => $context->id]
        );

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records('lti_tool_proxies');
            $DB->delete_records('lti_types', ['course' => SITEID]);
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->delete_records('lti_types', ['course' => $context->instanceid]);
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            // TODO MDL-85891: In the following code, I assumed that ltiid is repurposed to refer to `lti_resourcelink.id`.
            $linkids = $DB->get_fieldset('lti_resource_link', 'id', ['contextid' => $context->id]);
            [$insql, $inparams] = $DB->get_in_or_equal($linkids, SQL_PARAMS_NAMED);
            $DB->delete_records_select(
                'lti_submission',
                "ltiid $insql AND userid = :userid",
                array_merge($inparams, ['userid' => $userid]),
            );

            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->set_field('lti_tool_proxies', 'createdby', 0, ['createdby' => $userid]);
                $DB->set_field('lti_types', 'createdby', 0, ['course' => SITEID, 'createdby' => $userid]);
            } else if ($context->contextlevel == CONTEXT_COURSE) {
                $DB->set_field('lti_types', 'createdby', 0, ['course' => $context->instanceid, 'createdby' => $userid]);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        // TODO MDL-85891: In the following code, I assumed that ltiid is repurposed to refer to `lti_resourcelink.id`.
        $linkids = $DB->get_fieldset('lti_resource_link', 'id', ['contextid' => $context->id]);
        [$linksinsql, $linksinparams] = $DB->get_in_or_equal($linkids, SQL_PARAMS_NAMED);
        [$usersinsql, $usersinparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'lti_submission',
            "ltiid $linksinsql AND userid $usersinsql",
            array_merge($linksinparams, $usersinparams),
        );

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->set_field_select('lti_tool_proxies', 'createdby', 0, "createdby $usersinsql", $usersinparams);
            $DB->set_field_select(
                'lti_types',
                'createdby',
                0,
                "course = :siteid AND createdby $usersinsql",
                array_merge($usersinparams, ['siteid' => SITEID])
            );
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->set_field_select(
                'lti_types',
                'createdby',
                0,
                "course = :courseid AND createdby $usersinsql",
                array_merge($usersinparams, ['courseid' => $context->instanceid])
            );
        }
    }

    /**
     * Loop and export from a recordset.
     *
     * @param \moodle_recordset $recordset The recordset.
     * @param string $splitkey The record key to determine when to export.
     * @param mixed $initial The initial data to reduce from.
     * @param callable $reducer The function to return the dataset, receives current dataset, and the current record.
     * @param callable $export The function to export the dataset, receives the last value from $splitkey and the dataset.
     * @return void
     */
    protected static function recordset_loop_and_export(
        \moodle_recordset $recordset,
        string $splitkey,
        $initial,
        callable $reducer,
        callable $export
    ): void {
        $data = $initial;
        $lastid = null;

        foreach ($recordset as $record) {
            if ($lastid && $record->{$splitkey} != $lastid) {
                $export($lastid, $data);
                $data = $initial;
            }
            $data = $reducer($data, $record);
            $lastid = $record->{$splitkey};
        }
        $recordset->close();

        if (!empty($lastid)) {
            $export($lastid, $data);
        }
    }
}
