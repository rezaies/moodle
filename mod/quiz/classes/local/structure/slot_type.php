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
 * Defines the \mod_quiz\local\structure\slot_type class.
 *
 * @package    mod_quiz
 * @copyright  2018 onwards Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\local\structure;

defined('MOODLE_INTERNAL') || die();

/**
 * Class \mod_quiz\local\structure\slot_type
 *
 * Base class representing an instance of either {@link \mod_quiz\local\structure\slot_single}
 * or {@link \mod_quiz\local\structure\slot_random}.
 *
 * @package    mod_quiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class slot_type {
    /**
     * @var \stdClass Slot's properties.
     */
    protected $properties;

    /**
     * @var \stdClass The question this quiz slot hosts.
     */
    protected $question = null;

    /**
     * @var \stdClass The quiz this quiz slot belongs to.
     */
    protected $quiz = null;

    /**
     * @var \stdClass The question category object related to $this->properties->questioncategoryid.
     */
    protected $questioncategory = null;

    /**
     * @var \stdClass
     * @todo Remove this.
     */
    protected $slotdata;

    /**
     * slot_type constructor.
     *
     * @param \stdClass $slot
     */
    public function __construct($slot = null) {
        $this->properties = new \stdClass();

        if ($slot) {
            $this->load_slot($slot);
        }
    }

    /**
     * Loads quiz slot attributes into the slot object.
     * @param \stdClass $slot
     */
    public function load_slot($slot) {
        $this->properties = new \stdClass();
        $properties = array(
            'id', 'slot', 'quizid', 'page', 'requireprevious',
            'questionid', 'questioncategoryid', 'includingsubcategories',
            'tagid', 'maxmark');

        foreach ($properties as $property) {
            $this->properties->$property = isset($slot->$property) ? $slot->$property : null;
        }

        // $this->slotdata = $slot;
    }

    /**
     * Magic set method.
     *
     * Attempts to call a set_$key method if one exists otherwise falls back
     * to simply set the property.
     *
     * @param string $key property name
     * @param mixed $value value of the property
     */
    public function __set($key, $value) {
        if (method_exists($this, 'set_'.$key)) {
            $this->{'set_'.$key}($value);
        } else {
            $this->properties->{$key} = $value;
        }
    }

    /**
     * Magic get method.
     *
     * Attempts to call a get_$key method to return the property and ralls over
     * to return the raw property.
     *
     * @param string $key property name
     * @return mixed property value
     * @throws \coding_exception
     */
    public function __get($key) {
        if (method_exists($this, 'get_'.$key)) {
            return $this->{'get_'.$key}();
        }
        if (!property_exists($this->properties, $key)) {
            throw new \coding_exception('Undefined property requested');
        }
        return $this->properties->{$key};
    }

    /**
     * Magic isset method.
     *
     * PHP needs an isset magic method if you use the get magic method and
     * still want empty calls to work.
     *
     * @param string $key property name
     * @return bool Whether the property is set
     */
    public function __isset($key) {
        return !empty($this->properties->{$key});
    }

    /**
     * Sets the quiz object for the quiz slot.
     * It is not mandatory to set the quiz as the quiz slot can fetch it the first time it is accessed,
     * however it helps with the performance to set the quiz if you already have it.
     *
     * @param \stdClass $quiz The quiz object
     */
    public function set_quiz($quiz) {
        $this->quiz = $quiz;
        $this->properties->quizid = $quiz->id;
    }

    /**
     * Returns the quiz for this quiz slot. The quiz is fetched
     * the first time it is requested and then stored in a member
     * variable to be returned each subsequent time.
     *
     * This is a magical getter function that will be called whenever
     * the quiz property is accessed, e.g. $slot->quiz.
     *
     * @return \stdClass|bool|null
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_quiz() {
        global $DB;

        if (empty($this->quiz)) {
            if (empty($this->properties->quizid)) {
                throw new \coding_exception('quizid is not set.');
            }
            $this->quiz = $DB->get_record('quiz', array('id' => $this->properties->quizid));
        }

        return $this->quiz;
    }

    /**
     * Sets the quizid property of the quiz slot.
     * It is not allowed to call this method when the quizid is already set.
     *
     * This is a magical setter function that will be called whenever
     * the quizid property is set.
     *
     * @param int $quizid The quiz id
     * @throws \coding_exception
     */
    public function set_quizid($quizid) {
        if ($this->properties->quizid) {
            throw new \coding_exception('The quizid was already set. It is not allowed to set it again.');
        }
        $this->properties->quizid = $quizid;
    }

    /**
     * Returns the question that is hosted by the quiz slot.
     *
     * It is a magical getter function that will be called whenever
     * the question property is accessed, e.g. $slot->question.
     * @return \stdClass
     */
    public function get_question() {
        if (!isset($this->question)) {
            $this->fetch_question();
        }
        return $this->question;
    }

    /**
     * Inserts the quiz slot at the $page page.
     * It is required to call this function if you are building a quiz slot object from scratch.
     *
     * @param int $page The page that this slot will be inserted at.
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \dml_transaction_exception
     */
    public function insert($page = 0) {
        global $DB;

        $slots = $DB->get_records('quiz_slots', array('quizid' => $this->properties->quizid),
                'slot', 'id, slot, page');

        $trans = $DB->start_delegated_transaction();

        $maxpage = 1;
        $numonlastpage = 0;
        foreach ($slots as $slot) {
            if ($slot->page > $maxpage) {
                $maxpage = $slot->page;
                $numonlastpage = 1;
            } else {
                $numonlastpage += 1;
            }
        }

        if (is_int($page) && $page >= 1) {
            // Adding on a given page.
            $lastslotbefore = 0;
            foreach (array_reverse($slots) as $otherslot) {
                if ($otherslot->page > $page) {
                    $DB->set_field('quiz_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
                } else {
                    $lastslotbefore = $otherslot->slot;
                    break;
                }
            }
            $this->properties->slot = $lastslotbefore + 1;
            $this->properties->page = min($page, $maxpage + 1);

            quiz_update_section_firstslots($this->properties->quizid, 1, max($lastslotbefore, 1));
        } else {
            $lastslot = end($slots);
            $quiz = $this->get_quiz();
            if ($lastslot) {
                $this->properties->slot = $lastslot->slot + 1;
            } else {
                $this->properties->slot = 1;
            }
            if ($quiz->questionsperpage && $numonlastpage >= $quiz->questionsperpage) {
                $this->properties->page = $maxpage + 1;
            } else {
                $this->properties->page = $maxpage;
            }
        }

        $this->properties->id = $DB->insert_record('quiz_slots', $this->properties);
        $trans->allow_commit();
    }

    /**
     * Returns the display name for the question that is hosted in this quiz slot.
     *
     * @return string
     */
    public abstract function question_name();

    /**
     * The function that is used by get_question() to fetch slot's question from database.
     * This has to be implemented by subclasses.
     *
     * @return mixed
     */
    protected abstract function fetch_question();
}