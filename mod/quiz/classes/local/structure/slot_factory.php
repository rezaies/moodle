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
 * Defines the \mod_quiz\local\structure\slot_factory class.
 *
 * @package    mod_quiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\local\structure;

defined('MOODLE_INTERNAL') || die();

/**
 * Class slot_factory
 *
 * Factory class producing required subclasses of {@link \mod_quiz\local\structure\slot_type}.
 *
 * @package    mod_quiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slot_factory {

    /**
     * Builds a quiz slot object from the given data.
     *
     * @param \stdClass $slot
     * @return slot_random|slot_single
     */
    public static function build_from_slot_record($slot) {
        if ($slot->questionid === null) {
            return new slot_random($slot);
        } else {
            return new slot_single($slot);
        }
    }
}