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
 * Handle clicking on action links of the feedback alert.
 *
 * @module     core/cta_feedback
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

const SELECTORS = {
    actions: {
        give: 'a[data-action="give"]',
        remind: 'a[data-action="remind"]',
    },
};

/**
 * Attach the necessary event handlers to the action links
 *
 * @param {string} rootSelector The css selector of the container that contains action links.
 */
export const registerActions = rootSelector => {
    const root = document.querySelector(rootSelector);
    const giveAction = root.querySelector(SELECTORS.actions.give);
    const remindAction = root.querySelector(SELECTORS.actions.remind);

    giveAction.addEventListener('click', e => {
        e.preventDefault();
        giveFeedback();
    });
    remindAction.addEventListener('click', e => {
        e.preventDefault();
        remindLater();
    });

    giveAction.setAttribute('data-dismiss', 'alert');
    remindAction.setAttribute('data-dismiss', 'alert');
};

/**
 * The action function that is called when users choose to give feedback.
 */
const giveFeedback = () => {
    const surveyUrl = 'https://feedback.moodle.org';
    window.open(surveyUrl);

    const request = {
        methodname: 'core_cta_feedback_record_response',
        args: {
            action: 'give',
        }
    };

    Ajax.call([request]);
};

/**
 * The action function that is called when users choose the remind later action.
 */
const remindLater = () => {
    const request = {
        methodname: 'core_cta_feedback_record_action',
        args: {
            action: 'remind',
        }
    };

    Ajax.call([request]);
};
