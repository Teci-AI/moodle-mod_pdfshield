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
 * PDF Shield: backup structure.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The activity record, its description files and its PDF.
 */
class backup_pdfshield_activity_structure_step extends backup_activity_structure_step {

    /**
     * Structure of pdfshield.xml.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $pdfshield = new backup_nested_element('pdfshield', ['id'],
            ['name', 'intro', 'introformat', 'watermark', 'pageimages', 'timemodified']);
        $pdfshield->set_source_table('pdfshield', ['id' => backup::VAR_ACTIVITYID]);
        $pdfshield->annotate_files('mod_pdfshield', 'intro', null);
        $pdfshield->annotate_files('mod_pdfshield', 'content', null);
        return $this->prepare_activity_structure($pdfshield);
    }
}
