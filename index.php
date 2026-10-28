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
 * PDF Shield: the course's PDF Shield activities.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

\mod_pdfshield\event\course_module_instance_list_viewed::create(['context' => context_course::instance($course->id)])
    ->trigger();

$strplural = get_string('modulenameplural', 'mod_pdfshield');
$PAGE->set_url('/mod/pdfshield/index.php', ['id' => $course->id]);
$PAGE->set_title($course->shortname . ': ' . $strplural);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strplural);
echo $OUTPUT->header();
echo $OUTPUT->heading($strplural);

$instances = get_all_instances_in_course('pdfshield', $course);
if (!$instances) {
    notice(get_string('thereareno', 'moodle', $strplural), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';
$table->head = [get_string('name'), get_string('moduleintro')];
foreach ($instances as $instance) {
    $cm = get_fast_modinfo($course)->get_cm($instance->coursemodule);
    $link = html_writer::link(new moodle_url('/mod/pdfshield/view.php', ['id' => $cm->id]), format_string($instance->name),
        $cm->visible ? [] : ['class' => 'dimmed']);
    $table->data[] = [$link, format_module_intro('pdfshield', $instance, $cm->id)];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
