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
 * PDF Shield: the PDF for the viewer on the activity page. Only as a POST from that viewer, with the session key, to
 * someone who may view the activity; never when the activity shows pages as images.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci {@link https://www.teci.ai}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pdfshield\local\shield;

define('NO_DEBUG_DISPLAY', true);

require('../../config.php');

$id = required_param('id', PARAM_INT);

try {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'pdfshield');
    require_login($course, false, $cm, false, true);
    $context = context_module::instance($cm->id);
    require_capability('mod/pdfshield:view', $context);
} catch (Exception $e) {
    shield::refuse(403);
}
shield::require_viewer_request();

$instance = $DB->get_record('pdfshield', ['id' => $cm->instance], '*', MUST_EXIST);
$file = shield::main_file($context);
if (!$file || shield::effective($instance, 'pageimages')) {
    shield::refuse(404);
}

\core\session\manager::write_close();
shield::send_private_headers('application/octet-stream', $file->get_filesize());
$file->readfile();
