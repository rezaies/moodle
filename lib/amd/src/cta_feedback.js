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
import Notification from 'core/notification';

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
        const contextId = giveAction.dataset.contextId;
        giveFeedback(contextId)
            .then(() => {
                root.remove();
                return;
            })
            .catch(Notification.exception);
    });
    remindAction.addEventListener('click', e => {
        e.preventDefault();
        remindLater()
            .then(() => {
                root.remove();
                return;
            })
            .catch(Notification.exception);
    });
};

/**
 * Attach the necessary event handler to the give feedback link
 *
 * @param {string} elementSelector The css selector of the parent element
 */
export const registerFeedbackLink = elementSelector => {
    const element = document.querySelector(elementSelector);
    const giveAction = element.querySelector(SELECTORS.actions.give);

    giveAction.addEventListener('click', e => {
        e.preventDefault();
        const contextId = giveAction.dataset.contextId;
        giveFeedback(contextId)
            .then(() => {
                element.remove();
                return;
            })
            .catch(Notification.exception);
    });
};

/**
 * The action function that is called when users choose to give feedback.
 *
 * @param {number} contextId Context ID of the page the user is on
 * @returns {Promise<void>}
 */
const giveFeedback = async contextId => {
    let request = {
        methodname: 'core_cta_feedback_get_feedback_data',
        args: {
            contextid: contextId,
        }
    };
    const data = await Ajax.call([request])[0];
    const surveyUrl = 'https://feedback.moodle.org?lang=' + data.lang +
        '&moodle_url=' + encodeURIComponent(data.siteurl) +
        '&roles=' + encodeURIComponent(data.roles.join()) +
        '&moodle_version=' + encodeURIComponent(data.version) +
        '&theme=' + encodeURIComponent(data.theme) +
        '&theme_version=' + encodeURIComponent(data.themeversion);

    if (!window.open(surveyUrl)) {
        throw new Error('Unable to open popup');
    }

    request = {
        methodname: 'core_cta_feedback_record_action',
        args: {
            action: 'give',
        }
    };

    Ajax.call([request]);
};

/**
 * The action function that is called when users choose the remind later action.
 * @return {Promise<*>}
 */
const remindLater = () => {
    const request = {
        methodname: 'core_cta_feedback_record_action',
        args: {
            action: 'remind',
        }
    };

    return Ajax.call([request])[0];
};
