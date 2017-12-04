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
 * Raw event retrieval strategy.
 *
 * @package    core_calendar
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_calendar\local\event\strategies;

defined('MOODLE_INTERNAL') || die();

/**
 * Raw event retrieval strategy.
 *
 * This strategy is based on what used to be the calendar API's get_events function.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raw_event_retrieval_strategy implements raw_event_retrieval_strategy_interface {

    public function get_raw_events(
        array $usersfilter = null,
        array $groupsfilter = null,
        array $coursesfilter = null,
        array $categoriesfilter = null,
        array $whereconditions = null,
        array $whereparams = null,
        $ordersql = null,
        $offset = null,
        $limitnum = null,
        $ignorehidden = true
    ) {
        return $this->get_raw_events_legacy_implementation(
            !is_null($usersfilter) ? $usersfilter : true, // True means no filter in old implementation.
            !is_null($groupsfilter) ? $groupsfilter : true,
            !is_null($coursesfilter) ? $coursesfilter : true,
            !is_null($categoriesfilter) ? $categoriesfilter : true,
            $whereconditions,
            $whereparams,
            $ordersql,
            $offset,
            $limitnum,
            $ignorehidden
        );
    }

    /**
     * The legacy implementation with minor tweaks.
     *
     * @param array|int|boolean $users array of users, user id or boolean for all/no user events
     * @param array|int|boolean $groups array of groups, group id or boolean for all/no group events
     * @param array|int|boolean $courses array of courses, course id or boolean for all/no course events
     * @param string $whereconditions The conditions in the WHERE clause.
     * @param array $whereparams The parameters for the WHERE clause.
     * @param string $ordersql The ORDER BY clause.
     * @param int $offset Offset.
     * @param int $limitnum Limit.
     * @param boolean $ignorehidden whether to select only visible events or all events
     * @return array $events of selected events or an empty array if there aren't any (or there was an error)
     */
    protected function get_raw_events_legacy_implementation(
        $users,
        $groups,
        $courses,
        $categories,
        $whereconditions,
        $whereparams,
        $ordersql,
        $offset,
        $limitnum,
        $ignorehidden
    ) {
        global $DB;

        $params = array();
        $subqueryparams = array();

        // Quick test.
        if (empty($users) && empty($groups) && empty($courses) && empty($categories)) {
            return array();
        }

        // Array of filter conditions. To be concatenated by the OR operator.
        $filters = [];
        $subqueryfilters = [];

        // User filter.
        if ((is_array($users) && !empty($users)) or is_numeric($users)) {
            // Events from a number of users.
            list($insqlusers, $inparamsusers) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
            $filters[] = "(e.userid $insqlusers AND e.courseid = 0 AND e.groupid = 0 AND e.categoryid = 0)";
            $params = array_merge($params, $inparamsusers);
            list($insqlusers, $inparamsusers) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
            $subqueryfilters[] = "(ev.userid $insqlusers AND ev.courseid = 0 AND ev.groupid = 0 AND ev.categoryid = 0)";
            $subqueryparams = array_merge($subqueryparams, $inparamsusers);
        } else if ($users === true) {
            // Events from ALL users.
            $filters[] = "(e.userid != 0 AND e.courseid = 0 AND e.groupid = 0 AND e.categoryid = 0)";
            $subqueryfilters[] = "(ev.userid != 0 AND ev.courseid = 0 AND ev.groupid = 0 AND ev.categoryid = 0)";
        }
        // Boolean false (no users at all): We don't need to do anything.

        // Group filter.
        if ((is_array($groups) && !empty($groups)) or is_numeric($groups)) {
            // Events from a number of groups.
            list($insqlgroups, $inparamsgroups) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED);
            $filters[] = "e.groupid $insqlgroups";
            $params = array_merge($params, $inparamsgroups);
            list($insqlgroups, $inparamsgroups) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED);
            $subqueryfilters[] = "ev.groupid $insqlgroups";
            $subqueryparams = array_merge($subqueryparams, $inparamsgroups);
        } else if ($groups === true) {
            // Events from ALL groups.
            $filters[] = "e.groupid != 0";
            $subqueryfilters[] = "ev.groupid != 0";
        }
        // Boolean false (no groups at all): We don't need to do anything.

        // Course filter.
        if ((is_array($courses) && !empty($courses)) or is_numeric($courses)) {
            list($insqlcourses, $inparamscourses) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
            $filters[] = "(e.groupid = 0 AND e.courseid $insqlcourses)";
            $params = array_merge($params, $inparamscourses);
            list($insqlcourses, $inparamscourses) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
            $subqueryfilters[] = "(ev.groupid = 0 AND ev.courseid $insqlcourses)";
            $subqueryparams = array_merge($subqueryparams, $inparamscourses);
        } else if ($courses === true) {
            // Events from ALL courses.
            $filters[] = "(e.groupid = 0 AND e.courseid != 0)";
            $subqueryfilters[] = "(ev.groupid = 0 AND ev.courseid != 0)";
        }

        // Category filter.
        if ((is_array($categories) && !empty($categories)) or is_numeric($categories)) {
            list($insqlcategories, $inparamscategories) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED);
            $filters[] = "(e.groupid = 0 AND e.courseid = 0 AND e.categoryid $insqlcategories)";
            $params = array_merge($params, $inparamscategories);
            list($insqlcategories, $inparamscategories) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED);
            $subqueryfilters[] = "(ev.groupid = 0 AND ev.courseid = 0 AND ev.categoryid $insqlcategories)";
            $subqueryparams = array_merge($subqueryparams, $inparamscategories);
        } else if ($categories === true) {
            // Events from ALL categories.
            $filters[] = "(e.groupid = 0 AND e.courseid = 0 AND e.categoryid != 0)";
            $subqueryfilters[] = "(ev.groupid = 0 AND ev.courseid = 0 AND ev.categoryid != 0)";
        }

        // Security check: if, by now, we have NOTHING in $whereclause, then it means
        // that NO event-selecting clauses were defined. Thus, we won't be returning ANY
        // events no matter what. Allowing the code to proceed might return a completely
        // valid query with only time constraints, thus selecting ALL events in that time frame!
        if (empty($filters)) {
            return array();
        }

        // Build our clause for the filters.
        $filterclause = implode(' OR ', $filters);
        $subfilterclause = implode(' OR ', $subqueryfilters);

        // Array of where conditions for our query. To be concatenated by the AND operator.
        $whereconditions[] = "($filterclause)";
        $subwhereconditions[] = "($subfilterclause)";

        // Show visible only.
        if ($ignorehidden) {
            $whereconditions[] = "(e.visible = 1)";
            $subwhereconditions[] = "(ev.visible = 1)";
        }

        // Build the main query's WHERE clause.
        $whereclause = implode(' AND ', $whereconditions);
        $subwhereclause = implode(' AND ', $subwhereconditions);

        // Merge subquery parameters to the parameters of the main query.
        if (!empty($subqueryparams)) {
            $params = array_merge($params, $subqueryparams);
        }

        $subquery = "SELECT ev.modulename,
                            ev.instance,
                            ev.eventtype,
                            MIN(ev.priority) AS priority
                       FROM {event} ev
                      WHERE $subwhereclause
                   GROUP BY ev.modulename, ev.instance, ev.eventtype";

        // Build the main query.
        $sql = "SELECT e.*
                  FROM {event} e
            INNER JOIN ($subquery) fe
                    ON e.modulename = fe.modulename
                       AND e.instance = fe.instance
                       AND e.eventtype = fe.eventtype
                       AND (e.priority = fe.priority OR (e.priority IS NULL AND fe.priority IS NULL))
             LEFT JOIN {modules} m
                    ON e.modulename = m.name
                 WHERE (m.visible = 1 OR m.visible IS NULL) AND $whereclause
              ORDER BY " . ($ordersql ? $ordersql : "e.timestart");

        if (!empty($whereparams)) {
            $params = array_merge($params, $whereparams);
        }

        $events = $DB->get_records_sql($sql, $params, $offset, $limitnum);

        return  $events === false ? [] : $events;
    }
}
