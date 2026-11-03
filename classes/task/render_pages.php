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

namespace mod_pdfshield\task;

use mod_pdfshield\local\shield;

/**
 * Renders all pages of an activity's PDF as images after it is saved, so readers rarely wait for a page.
 * Pages not rendered yet are rendered when first shown anyway.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci {@link https://www.teci.ai}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class render_pages extends \core\task\adhoc_task {
    /**
     * Renders the pages, if the activity still exists and still shows pages as images.
     */
    public function execute() {
        global $DB;
        $cmid = (int) ($this->get_custom_data()->cmid ?? 0);
        $cm = get_coursemodule_from_id('pdfshield', $cmid);
        if (!$cm || !$instance = $DB->get_record('pdfshield', ['id' => $cm->instance])) {
            return;
        }
        $context = \context_module::instance($cm->id);
        $file = shield::main_file($context);
        if (!$file || !shield::effective($instance, 'pageimages')) {
            return;
        }
        if (!shield::page_count($instance, $file)) {
            mtrace("PDF Shield: course module {$cmid} has no PDF Ghostscript can read; no page images.");
            return;
        }
        $rendered = shield::render_all_pages($context, $file);
        mtrace("PDF Shield: {$rendered} page images rendered for course module {$cmid}.");
    }
}
