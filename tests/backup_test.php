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
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore of a PDF Shield activity.
 *
 * @package    mod_pdfshield
 * @category   test
 * @copyright  2026 Teci {@link https://www.teci.ai}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_pdfshield_activity_structure_step
 * @covers     \restore_pdfshield_activity_structure_step
 */
final class backup_test extends \advanced_testcase {
    /**
     * The activity, its settings and its PDF are copied into another course.
     */
    public function test_backup_and_restore(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();
        $act = $this->getDataGenerator()->create_module('pdfshield', ['course' => $source->id, 'name' => 'Leaves',
            'watermark' => 1, 'pageimages' => 0]);
        $file = shield::main_file(\context_module::instance($act->cmid));

        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $act->cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $rc = new \restore_controller(
            $backupid,
            $target->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $copies = get_coursemodules_in_course('pdfshield', $target->id);
        $this->assertCount(1, $copies);
        $copy = reset($copies);
        $record = $DB->get_record('pdfshield', ['id' => $copy->instance]);
        $this->assertSame('Leaves', $record->name);
        $this->assertEquals(1, $record->watermark);
        $this->assertEquals(0, $record->pagecount, 'counted again for the copy');
        $copyfile = shield::main_file(\context_module::instance($copy->id));
        $this->assertSame($file->get_contenthash(), $copyfile->get_contenthash());
    }
}
