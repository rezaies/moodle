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
 * Contains class containing unit tests for mod/chat/lib.php.
 *
 * @package mod_chat
 * @category test
 * @copyright 2017 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class containing unit tests for mod/chat/lib.php.
 *
 * @package mod_chat
 * @category test
 * @copyright 2017 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_chat_lib_testcase extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_yesterday() {
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a chat.
        $chat = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
            'chattime' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $chat->id, CHAT_EVENT_TYPE_CHATTIME);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_chat_core_calendar_provide_event_action($event, $factory);

        // Confirm the event is not shown at all.
        $this->assertNull($actionevent);
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_yesterday_for_user() {
        global $CFG;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a chat.
        $chat = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
            'chattime' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $chat->id, CHAT_EVENT_TYPE_CHATTIME);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users have mod/chat:view capability by default.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_chat_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event is not shown at all.
        $this->assertNull($actionevent);
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_today() {
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a chat.
        $chat = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
            'chattime' => usergetmidnight(time())));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $chat->id, CHAT_EVENT_TYPE_CHATTIME);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_chat_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_today_for_user() {
        global $CFG;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a chat.
        $chat = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
            'chattime' => usergetmidnight(time())));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $chat->id, CHAT_EVENT_TYPE_CHATTIME);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users have mod/chat:view capability by default.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_chat_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_tonight() {
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a chat.
        $chat = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
            'chattime' => usergetmidnight(time()) + (23 * HOURSECS)));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $chat->id, CHAT_EVENT_TYPE_CHATTIME);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_chat_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_tonight_for_user() {
        global $CFG;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a chat.
        $chat = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
            'chattime' => usergetmidnight(time()) + (23 * HOURSECS)));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $chat->id, CHAT_EVENT_TYPE_CHATTIME);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users have mod/chat:view capability by default.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_chat_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_tomorrow() {
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a chat.
        $chat = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
            'chattime' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $chat->id, CHAT_EVENT_TYPE_CHATTIME);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_chat_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_tomorrow_for_user() {
        global $CFG;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a chat.
        $chat = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
            'chattime' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $chat->id, CHAT_EVENT_TYPE_CHATTIME);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users have mod/chat:view capability by default.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_chat_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_chat_core_calendar_provide_event_action_chattime_event_different_timezones() {
        global $CFG;

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        $hour = gmdate('H');

        // This could have been much easier if MDL-37327 were implemented.
        // We don't know when this test is being ran and there is no standard way to
        // mock the time() function (MDL-37327 to handle that). Therefore,
        if ($hour < 10) {
            $timezone1 = 'Europe/London';       // GMT or GMT +01:00
            $timezone2 = 'Pacific/Pago_Pago';   // GMT -11:00
        } else if ($hour < 11) {
            $timezone1 = 'Pacific/Kiritimati';  // GMT +14:00
            $timezone2 = 'America/Sao_Paulo';   // GMT -03:00
        } else {
            $timezone1 = 'Pacific/Kiritimati';  // GMT +14:00
            $timezone2 = 'Europe/London';       // GMT or GMT +01:00
        }

        $this->setTimezone($timezone2);

        // Enrol 2 students with different timezones in the course.
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student', (object)['timezone' => $timezone1]);
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student', (object)['timezone' => $timezone2]);

        // Create a chat.
        $chat1 = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
                'chattime' => mktime(1, 0, 0)));    // This is always yesterday in timezone1 time
                                                    // and always today in timezone2 time.

        // Create a chat.
        $chat2 = $this->getDataGenerator()->create_module('chat', array('course' => $course->id,
                'chattime' => mktime(1, 0, 0) + DAYSECS));  // This is always today in timezone1 time
                                                            // and always tomorrow in timezone2 time.

        // Create calendar events for the 2 chats above.
        $event1 = $this->create_action_event($course->id, $chat1->id, CHAT_EVENT_TYPE_CHATTIME);
        $event2 = $this->create_action_event($course->id, $chat2->id, CHAT_EVENT_TYPE_CHATTIME);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users have mod/chat:view capability by default.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for student1.
        $actionevent11 = mod_chat_core_calendar_provide_event_action($event1, $factory, $student1->id);
        $actionevent12 = mod_chat_core_calendar_provide_event_action($event1, $factory, $student2->id);
        $actionevent21 = mod_chat_core_calendar_provide_event_action($event2, $factory, $student1->id);
        $actionevent22 = mod_chat_core_calendar_provide_event_action($event2, $factory, $student2->id);

        // Confirm event1 is not shown to student1 at all.
        $this->assertNull($actionevent11);

        // Confirm event1 was decorated for student2 and it is actionable.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent12);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent12->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent12->get_url());
        $this->assertEquals(1, $actionevent12->get_item_count());
        $this->assertTrue($actionevent12->is_actionable());

        // Confirm event2 was decorated for student1 and it is actionable.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent21);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent21->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent21->get_url());
        $this->assertEquals(1, $actionevent21->get_item_count());
        $this->assertTrue($actionevent21->is_actionable());

        // Confirm event2 was decorated for student2 and it is not actionable.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent22);
        $this->assertEquals(get_string('enterchat', 'chat'), $actionevent22->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent22->get_url());
        $this->assertEquals(1, $actionevent22->get_item_count());
        $this->assertFalse($actionevent22->is_actionable());
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid
     * @param int $instanceid The chat id.
     * @param string $eventtype The event type. eg. ASSIGN_EVENT_TYPE_DUE.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'chat';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return calendar_event::create($event);
    }
}
