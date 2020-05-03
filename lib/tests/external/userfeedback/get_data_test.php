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
 * External functions test for get_data.
 *
 * @package    core
 * @category   test
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Class get_data_testcase
 *
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core\external\userfeedback\get_data
 */
class get_data_testcase extends externallib_advanced_testcase {

    /**
     * Test the behaviour of get_data().
     *
     * @covers ::execute
     */
    public function test_record_action_system() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = context_system::instance();

        $this->setUser($user);

        // Call the WS and check the requested data is returned as expected.
        $result = \core\external\userfeedback\get_data::execute($context->id);
        $result = external_api::clean_returnvalue(\core\external\userfeedback\get_data::execute_returns(), $result);

        $this->assertEquals('en', $result['lang']);
        $this->assertEquals('https://www.example.com/moodle', $result['siteurl']);
        $this->assertEquals('https://feedback.moodle.org/lms', $result['feedbackurl']);
        $this->assertEquals(['student'], $result['roles']);
        $this->assertEquals('boost', $result['theme']);
    }

    /**
     * Test the behaviour of get_data() in a course with a course theme.
     *
     * @covers ::execute
     */
    public function test_record_action_course_theme() {
        $this->resetAfterTest();

        // Enable course themes.
        set_config('allowcoursethemes', 1);

        $course = $this->getDataGenerator()->create_course(['theme' => 'classic']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = context_course::instance($course->id);

        $this->setUser($user);

        // Call the WS and check the requested data is returned as expected.
        $result = \core\external\userfeedback\get_data::execute($context->id);
        $result = external_api::clean_returnvalue(\core\external\userfeedback\get_data::execute_returns(), $result);

        $this->assertEquals('classic', $result['theme']);
    }

    /**
     * Test the behaviour of get_data() when a custom feedback url is set.
     *
     * @covers ::execute
     */
    public function test_record_action_custom_feedback_url() {
        $this->resetAfterTest();

        // Enable course themes.
        set_config('userfeedback_url', 'https://feedback.moodle.org/abc');

        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();

        $this->setUser($user);

        // Call the WS and check the requested data is returned as expected.
        $result = \core\external\userfeedback\get_data::execute($context->id);
        $result = external_api::clean_returnvalue(\core\external\userfeedback\get_data::execute_returns(), $result);

        $this->assertEquals('https://feedback.moodle.org/abc', $result['feedbackurl']);
    }
}
