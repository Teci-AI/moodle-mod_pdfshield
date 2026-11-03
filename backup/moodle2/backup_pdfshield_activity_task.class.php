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
 * PDF Shield: backup task.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci {@link https://www.teci.ai}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/pdfshield/backup/moodle2/backup_pdfshield_stepslib.php');

/**
 * Backs up one PDF Shield activity: its settings, description and PDF (page images are made again when needed).
 */
class backup_pdfshield_activity_task extends backup_activity_task {
    /**
     * No particular settings.
     */
    protected function define_my_settings() {
    }

    /**
     * The activity's structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_pdfshield_activity_structure_step('pdfshield_structure', 'pdfshield.xml'));
    }

    /**
     * Links to the activity in course content, encoded for restore.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace(
            '/(' . $base . '\/mod\/pdfshield\/index.php\?id\=)([0-9]+)/',
            '$@PDFSHIELDINDEX*$2@$',
            $content
        );
        return preg_replace(
            '/(' . $base . '\/mod\/pdfshield\/view.php\?id\=)([0-9]+)/',
            '$@PDFSHIELDVIEWBYID*$2@$',
            $content
        );
    }
}
