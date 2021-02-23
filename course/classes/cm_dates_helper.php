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
 * Contains the helper class for working with cm_dates
 *
 * @package   core_course
 * @copyright Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types = 1);

namespace core_course;

use cm_info;

/**
 * Class cm_dates_helper
 *
 * @copyright Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_dates_helper {

    /**
     * Returns a list of important dates in the given module for the user.
     *
     * @param cm_info $cm The course module information.
     * @param int $userid The user ID.
     * @return array[]
     */
    public static function get_dates(cm_info $cm, int $userid): array {
        $cmdatesclassname = activity_dates::get_cm_dates_classname($cm->modname);
        if (!$cmdatesclassname) {
            return [];
        }

        /** @var activity_dates $dates */
        $dates = new $cmdatesclassname($cm, $userid);
        return $dates->get_dates();
    }
}
