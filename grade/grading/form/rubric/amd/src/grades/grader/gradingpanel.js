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
 * Grading panel for gradingform_rubric.
 *
 * @module     gradingform_rubric/grades/grader/gradingpanel
 * @package    gradingform_rubric
 * @copyright  2019 Mathew May <mathew.solutions>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {normaliseResult} from 'core_grades/grades/grader/gradingpanel/normalise';


// Note: We use jQuery.serializer here until we can rewrite Ajax to use XHR.send()
import jQuery from 'jquery';

export const fetchCurrentGrade = (component, contextid, itemname, gradeduserid) => {
    return fetchMany([{
        methodname: `gradingform_rubric_grader_gradingpanel_fetch`,
        args: {
            component,
            contextid,
            itemname,
            gradeduserid,
        },
    }])[0];
};


export const storeCurrentGrade = async(component, contextid, itemname, gradeduserid, rootNode) => {
    const form = rootNode.querySelector('form');

    return normaliseResult(await fetchMany([{
        methodname: `gradingform_rubric_grader_gradingpanel_store`,
        args: {
            component,
            contextid,
            itemname,
            gradeduserid,
            formdata: jQuery(form).serialize(),
        },
    }])[0]);
};
