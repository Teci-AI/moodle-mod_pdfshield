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
 * PDF Shield: the activity page, with the viewer.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pdfshield\local\shield;

require('../../config.php');

$id = optional_param('id', 0, PARAM_INT);
$p = optional_param('p', 0, PARAM_INT);
if ($p) {
    $instance = $DB->get_record('pdfshield', ['id' => $p], '*', MUST_EXIST);
    [$course, $cm] = get_course_and_cm_from_instance($instance->id, 'pdfshield');
} else {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'pdfshield');
    $instance = $DB->get_record('pdfshield', ['id' => $cm->instance], '*', MUST_EXIST);
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/pdfshield:view', $context);

pdfshield_view($instance, $course, $cm, $context);

$PAGE->set_url('/mod/pdfshield/view.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . format_string($instance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($instance);
$PAGE->add_body_class('mod-pdfshield-viewing');

$file = shield::main_file($context);
$images = shield::effective($instance, 'pageimages');
$problem = null;
$pagecount = 0;
if (!$file) {
    $problem = get_string('filenotfound', 'mod_pdfshield');
} else if ($images) {
    // Page images were asked for: without Ghostscript the PDF is not sent instead.
    $pagecount = shield::ghostscript_available() ? shield::page_count($instance, $file) : 0;
    if (!$pagecount) {
        $problem = get_string('pageimagesneeded', 'mod_pdfshield');
        if (has_capability('moodle/course:manageactivities', $context)) {
            $problem .= ' ' . get_string('pageimagesunavailable', 'mod_pdfshield');
        }
    }
}

echo $OUTPUT->header();
if ($problem) {
    echo $OUTPUT->notification($problem, \core\output\notification::NOTIFY_WARNING, false);
} else {
    $search = !$images && shield::search_allowed();
    echo $OUTPUT->render_from_template('mod_pdfshield/viewer', ['search' => $search, 'images' => $images]);
    $PAGE->requires->js_call_amd('mod_pdfshield/viewer', 'init', [[
        'cmid' => (int) $cm->id,
        'mode' => $images ? 'images' : 'pdf',
        'pages' => $pagecount,
        'search' => $search,
        'watermark' => shield::effective($instance, 'watermark') ? shield::watermark_text() : '',
    ]]);
}
echo $OUTPUT->footer();
