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
 * Defines the \mod_quiz\local\structure\slot_random class.
 *
 * @package    mod_quiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\local\structure;

defined('MOODLE_INTERNAL') || die();

/**
 * Class slot_random, represents a random question slot type.
 *
 * @package    mod_quiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slot_random extends slot_type {

    /**
     * slot_random constructor.
     *
     * @param \stdClass $slot
     */
    public function __construct($slot = null) {
        parent::__construct($slot);
        $this->properties->questionid = null;
    }

    /**
     * Returns the question category object related to this slot.
     *
     * @return \stdClass
     * @throws \coding_exception
     */
    public function get_questioncategory() {
        global $DB;

        if (empty($this->questioncategory)) {
            if (empty($this->properties->questioncategoryid)) {
                throw new \coding_exception('questioncategoryid is not set.');
            }
            $this->questioncategory = $DB->get_record('question_categories', array('id' => $this->properties->questioncategoryid));
        }

        return $this->questioncategory;
    }

    /**
     * Random questions always get a question name that is Random (cateogryname).
     * This function is a centralised place to calculate that, given the category.
     *
     * @return string
     */
    public function question_name() {
        // Todo: add support for tagid.
        $questioncategory = $this->get_questioncategory();
        if ($this->properties->includingsubcategories) {
            $string = 'randomqplusname';
        } else {
            $string = 'randomqname';
        }
        return get_string($string, 'mod_quiz', shorten_text($questioncategory->name, 100));
    }

    /**
     * THIS IS A STUB.
     *
     * @todo Implement this
     * @return mixed|void
     */
    protected function fetch_question() {
        static $ccccc = 0;

        $question = new \stdClass();
        $question->id = 9999999 + $ccccc++;
        $question->qtype = 'random';
        $question->length = 1;

        $this->question = $question;
    }
}