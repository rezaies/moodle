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

const Selectors = {
    regions: {
        cta: '.cta.alert.userfeedback',
    },
    actions: {},
};
Selectors.actions.give = `${Selectors.regions.cta} [data-action="give"]`;
Selectors.actions.remind = `${Selectors.regions.cta} [data-action="remind"]`;

/**
 * Attach the necessary event handlers to the action links
 */
export const registerEventListeners = () => {
    document.addEventListener('click', e => {
        const giveAction = e.target.closest(Selectors.actions.give);
        if (giveAction) {
            e.preventDefault();
            giveFeedback()
                .then(() => {
                    return recordAction('give');
                })
                .then(() => {
                    const root = giveAction.closest(Selectors.regions.cta);
                    root.remove();
                    return;
                })
                .catch(Notification.exception);
        }

        const remindAction = e.target.closest(Selectors.actions.remind);
        if (remindAction) {
            e.preventDefault();
            recordAction('remind')
                .then(() => {
                    const root = remindAction.closest(Selectors.regions.cta);
                    root.remove();
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
        methodname: 'core_get_userfeedback_url',
        args: {
            contextid: M.cfg.contextid,
        }
    }])[0]
        .then(url => {
            if (!window.open(url)) {
                throw new Error('Unable to open popup');
            }
            return;
        });
};

/**
 * Record the action that the user took.
 *
 * @param {string} action The action that the user took. Either give or remind.
 * @returns {Promise<null>}
 */
const recordAction = action => {
    return Ajax.call([{
        methodname: 'core_create_userfeedback_action_record',
        args: {
            action,
        }
    }])[0];
};
