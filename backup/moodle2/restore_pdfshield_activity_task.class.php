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
 * PDF Shield: restore task.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/pdfshield/backup/moodle2/restore_pdfshield_stepslib.php');

/**
 * Restores one PDF Shield activity.
 */
class restore_pdfshield_activity_task extends restore_activity_task {
    /**
     * No particular settings.
     */
    protected function define_my_settings() {
    }

    /**
     * The activity's structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_pdfshield_activity_structure_step('pdfshield_structure', 'pdfshield.xml'));
    }

    /**
     * Content with links to decode.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('pdfshield', ['intro'], 'pdfshield')];
    }

    /**
     * Link rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('PDFSHIELDVIEWBYID', '/mod/pdfshield/view.php?id=$1', 'course_module'),
            new restore_decode_rule('PDFSHIELDINDEX', '/mod/pdfshield/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log rules (legacy logs).
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('pdfshield', 'add', 'view.php?id={course_module}', '{pdfshield}'),
            new restore_log_rule('pdfshield', 'update', 'view.php?id={course_module}', '{pdfshield}'),
            new restore_log_rule('pdfshield', 'view', 'view.php?id={course_module}', '{pdfshield}'),
        ];
    }

    /**
     * Course-level log rules (legacy logs).
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [new restore_log_rule('pdfshield', 'view all', 'index.php?id={course}', null)];
    }
}
