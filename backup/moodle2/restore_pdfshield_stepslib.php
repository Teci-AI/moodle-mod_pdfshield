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
 * PDF Shield: restore structure.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The activity record, its description files and its PDF.
 */
class restore_pdfshield_activity_structure_step extends restore_activity_structure_step {

    /**
     * Paths in pdfshield.xml.
     *
     * @return array
     */
    protected function define_structure() {
        return $this->prepare_activity_structure([new restore_path_element('pdfshield', '/activity/pdfshield')]);
    }

    /**
     * Creates the activity record (the page count is made again for the restored PDF).
     *
     * @param array $data
     */
    protected function process_pdfshield($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->contenthash = '';
        $data->pagecount = 0;
        $newid = $DB->insert_record('pdfshield', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Files after the record.
     */
    protected function after_execute() {
        $this->add_related_files('mod_pdfshield', 'intro', null);
        $this->add_related_files('mod_pdfshield', 'content', null);
    }
}
