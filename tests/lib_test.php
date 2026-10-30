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

namespace mod_pdfshield;

use mod_pdfshield\local\shield;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/pdfshield/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * The activity's callbacks: saving with locked settings, deleting, drag and drop, completion, calendar.
 *
 * @package    mod_pdfshield
 * @category   test
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::pdfshield_update_instance
 * @covers     ::pdfshield_delete_instance
 * @covers     ::pdfshield_dndupload_handle
 * @covers     ::pdfshield_view
 * @covers     ::mod_pdfshield_core_calendar_provide_event_action
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('pdfshield_update_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('pdfshield_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('pdfshield_dndupload_handle')]
#[\PHPUnit\Framework\Attributes\CoversFunction('pdfshield_view')]
#[\PHPUnit\Framework\Attributes\CoversFunction('mod_pdfshield_core_calendar_provide_event_action')]
final class lib_test extends \advanced_testcase {
    /**
     * A course with completion enabled, as admin.
     *
     * @return \stdClass
     */
    private function course(): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        return $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
    }

    /**
     * Form data to save an activity.
     *
     * @param \stdClass $act
     * @param int $watermark
     * @return \stdClass
     */
    private function form_data(\stdClass $act, int $watermark): \stdClass {
        return (object) ['instance' => $act->id, 'coursemodule' => $act->cmid, 'course' => $act->course,
            'name' => $act->name, 'intro' => '', 'introformat' => FORMAT_HTML, 'watermark' => $watermark,
            'pageimages' => 0];
    }

    /**
     * Locked for a while, then given back: the activity's own value survives a save while locked.
     */
    public function test_locked_setting_keeps_own_value(): void {
        global $DB;
        $course = $this->course();
        $act = $this->getDataGenerator()->create_module('pdfshield', ['course' => $course->id, 'watermark' => 0]);
        set_config('watermark', 1, 'mod_pdfshield');
        set_config('watermark_locked', 1, 'mod_pdfshield');
        pdfshield_update_instance($this->form_data($act, 1));
        $saved = $DB->get_record('pdfshield', ['id' => $act->id]);
        $this->assertEquals(0, $saved->watermark, 'own value kept');
        $this->assertTrue(shield::effective($saved, 'watermark'), 'the site value applies meanwhile');
        set_config('watermark_locked', 0, 'mod_pdfshield');
        $this->assertFalse(shield::effective($saved, 'watermark'), 'own value again once unlocked');
        pdfshield_update_instance($this->form_data($act, 1));
        $this->assertEquals(1, $DB->get_field('pdfshield', 'watermark', ['id' => $act->id]), 'teachers choose again');
    }

    /**
     * Deleting the activity removes its PDF and page images.
     */
    public function test_delete_removes_files(): void {
        $course = $this->course();
        $act = $this->getDataGenerator()->create_module('pdfshield', ['course' => $course->id]);
        $context = \context_module::instance($act->cmid);
        $this->assertNotNull(shield::main_file($context));
        $this->assertTrue(pdfshield_delete_instance($act->id));
        $this->assertEmpty(get_file_storage()->get_area_files($context->id, 'mod_pdfshield', false, false, 'id', false));
    }

    /**
     * A file dropped on the course page must be a real PDF; a PDF gets the site defaults.
     */
    public function test_drag_and_drop(): void {
        global $DB, $USER;
        $course = $this->course();
        set_config('watermark', 1, 'mod_pdfshield');
        $drop = function (string $content) use ($course, $DB, $USER) {
            $draft = file_get_unused_draft_itemid();
            get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user', 'filearea' => 'draft', 'itemid' => $draft, 'filepath' => '/',
                'filename' => 'drop.pdf'], $content);
            $cmid = add_course_module((object) ['course' => $course->id, 'instance' => 0, 'section' => 0,
                'module' => $DB->get_field('modules', 'id', ['name' => 'pdfshield'])]);
            return pdfshield_dndupload_handle((object) ['course' => $course, 'displayname' => 'Dropped',
                'coursemodule' => $cmid, 'draftitemid' => $draft]);
        };
        $id = $drop(\mod_pdfshield_generator::make_pdf(1));
        $this->assertEquals(1, $DB->get_field('pdfshield', 'watermark', ['id' => $id]));
        $this->expectException(\moodle_exception::class);
        $drop("%!PS\nshowpage\n");
    }

    /**
     * Viewing completes a "view to complete" activity and never changes a manual one.
     */
    public function test_view_and_completion(): void {
        global $DB;
        $course = $this->course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $viewed = $this->getDataGenerator()->create_module('pdfshield', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]);
        $manual = $this->getDataGenerator()->create_module('pdfshield', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course->id, $student->id);
        $this->setUser($student);
        $sink = $this->redirectEvents();
        foreach ([$viewed, $manual] as $act) {
            pdfshield_view(
                $DB->get_record('pdfshield', ['id' => $act->id]),
                $course,
                $modinfo->get_cm($act->cmid),
                \context_module::instance($act->cmid)
            );
        }
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\course_module_viewed);
        $this->assertCount(2, $events);
        $this->assertEquals(
            COMPLETION_COMPLETE,
            $completion->get_data($modinfo->get_cm($viewed->cmid), false, $student->id)->completionstate
        );
        $this->assertEquals(
            COMPLETION_INCOMPLETE,
            $completion->get_data($modinfo->get_cm($manual->cmid), false, $student->id)->completionstate
        );
    }

    /**
     * The expected completion date is in the calendar, with a link to the activity while not completed.
     */
    public function test_calendar_event(): void {
        global $DB;
        $course = $this->course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $expected = time() + WEEKSECS;
        $act = $this->getDataGenerator()->create_module('pdfshield', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL, 'completionexpected' => $expected]);
        $event = $DB->get_record('event', ['modulename' => 'pdfshield', 'instance' => $act->id,
            'eventtype' => \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED]);
        $this->assertEquals($expected, $event->timestart);
        $action = mod_pdfshield_core_calendar_provide_event_action(
            \calendar_event::load($event->id),
            new \core_calendar\action_factory(),
            $student->id
        );
        $this->assertNotNull($action);
        $this->assertStringContainsString('/mod/pdfshield/view.php?id=' . $act->cmid, $action->get_url()->out(false));
    }
}
