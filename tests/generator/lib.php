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
 * PDF Shield: test data generator.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates PDF Shield activities with a PDF, for tests.
 */
class mod_pdfshield_generator extends testing_module_generator {
    /**
     * Creates an activity. Without 'pdf' (PDF content), a PDF with the given pages ('pages', default 3) is made.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        global $USER;
        $record = (array) $record;
        $pdf = $record['pdf'] ?? self::make_pdf((int) ($record['pages'] ?? 3));
        unset($record['pdf'], $record['pages']);
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(['contextid' => context_user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftitemid, 'filepath' => '/',
            'filename' => 'document.pdf'], $pdf);
        $record += ['files' => $draftitemid, 'watermark' => 0, 'pageimages' => 0];
        return parent::create_instance($record, (array) $options);
    }

    /**
     * A PDF with numbered pages of text (page 2 mentions photosynthesis twice, for search tests).
     *
     * @param int $pages
     * @return string PDF content
     */
    public static function make_pdf(int $pages = 3): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $doc = new pdf();
        $doc->setPrintHeader(false);
        $doc->setPrintFooter(false);
        $doc->SetFont('helvetica', '', 14);
        for ($i = 1; $i <= $pages; $i++) {
            $doc->AddPage();
            $doc->Write(0, "Shield test page {$i}\n\n");
            if ($i === 2) {
                $doc->Write(0, "Photosynthesis turns light into food. Plants rely on photosynthesis every day.\n");
            } else {
                $doc->Write(0, "Roots take water and minerals from the soil.\n");
            }
        }
        return $doc->Output('', 'S');
    }
}
