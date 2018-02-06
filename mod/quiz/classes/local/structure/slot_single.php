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
 * Defines the \mod_quiz\local\structure\slot_single class.
 *
 * @package    mod_quiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\local\structure;

defined('MOODLE_INTERNAL') || die();

/**
 * Class slot_single, represents a single question slot type.
 *
 * @package    mod_quiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slot_single extends slot_type {

    /**
     * slot_single constructor.
     *
     * @param \stdClass $slot
     */
    public function __construct($slot) {
        parent::__construct($slot);
        $this->properties->questioncategoryid = null;
        $this->properties->includingsubcategories = null;
        $this->properties->tagid = null;
    }

    public function question_name() {
        return $this->get_question()->name;
    }

    protected function fetch_question() {
        global $DB;

        $this->question = $DB->get_record('question', array('id' => $this->properties->questionid));
    }
}
