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

const selectors = {
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
export const registerEventListeners = rootSelector => {
    const root = document.querySelector(rootSelector);

    root.addEventListener('click', e => {
        const giveAction = e.target.closest(selectors.actions.give);
        if (giveAction) {
            e.preventDefault();
            const record = !!giveAction.dataset.record;
            const hide = !!giveAction.dataset.hide;
            giveFeedback()
                .then(() => {
                    return record ? recordAction('give') : null;
                })
                .then(() => {
                    if (hide) {
                        root.remove();
                    }
                    return;
                })
                .catch(Notification.exception);
        }

        const remindAction = e.target.closest(selectors.actions.remind);
        if (remindAction) {
            e.preventDefault();
            const record = !!remindAction.dataset.record;
            const hide = !!remindAction.dataset.hide;
            (async() => {
                return record ? recordAction('remind') : null;
            })()
                .then(() => {
                    if (hide) {
                        root.remove();
                    }
                    return;
                })
                .catch(Notification.exception);
        }
    });
};

/**
 * The action function that is called when users choose to give feedback.
 *
 * @returns {Promise<void>}
 */
const giveFeedback = () => {
    return Ajax.call([{
        methodname: 'core_get_userfeedback_data',
        args: {
            contextid: M.cfg.contextid,
        }
    }])[0]
        .then(data => {
            return getSurveyUrl(data);
        })
        .then(url => {
            if (!window.open(url)) {
                throw new Error('Unable to open popup');
            }
            return;
        });
};

/**
 * Generates the survey's URL.
 *
 * @param {Object} data The data we need to generate survey url.
 * @param {string} data.feedbackurl The base url of the feedback site.
 * @param {string} data.lang The language code.
 * @param {string} data.siteurl The wwwroot of Moodle.
 * @param {string} data.roles List of user roles.
 * @param {string} data.version List of user roles.
 * @param {string} data.theme The name of the theme the user use.
 * @param {string} data.themeversion Version of the theme.
 * @returns {string}
 */
const getSurveyUrl = data => {
    const firstSeparator = data.feedbackurl.indexOf('?') == -1 ? '?' : '&';

    return data.feedbackurl +
        firstSeparator + 'lang=' + data.lang +
        '&moodle_url=' + encodeURIComponent(data.siteurl) +
        '&roles=' + encodeURIComponent(data.roles.join()) +
        '&moodle_version=' + encodeURIComponent(data.version) +
        '&theme=' + encodeURIComponent(data.theme) +
        '&theme_version=' + encodeURIComponent(data.themeversion);
};

/**
 * Record the action that the user took.
 *
 * @param {string} action The action that the user took. Either give or remind.
 * @returns {Promise<null>}
 */
const recordAction = (action) => {
    return Ajax.call([{
        methodname: 'core_create_userfeedback_action_record',
        args: {
            action,
            contextid: M.cfg.contextid,
        }
    }])[0];
};
