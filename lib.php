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
 * PDF Shield: the activity's callbacks.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci {@link https://www.teci.ai}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_pdfshield\local\shield;

/**
 * Features this activity supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed true or false, a value for some features, null if unknown
 */
function pdfshield_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
            return false;
        default:
            return null;
    }
}

/**
 * Adds an activity.
 *
 * @param stdClass $data form data
 * @param mod_pdfshield_mod_form|null $mform
 * @return int new instance id
 */
function pdfshield_add_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    $data->watermark = empty($data->watermark) ? 0 : 1;
    $data->pageimages = empty($data->pageimages) ? 0 : 1;
    $data->contenthash = ''; // Pages not counted yet.
    $data->pagecount = 0;
    $data->id = $DB->insert_record('pdfshield', $data);
    // The context needs the instance id in the course module first.
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $data->coursemodule]);
    pdfshield_save_file($data);
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'pdfshield',
        $data->id,
        $data->completionexpected ?? null
    );
    return $data->id;
}

/**
 * Updates an activity.
 *
 * @param stdClass $data form data
 * @param mod_pdfshield_mod_form|null $mform
 * @return bool
 */
function pdfshield_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    // A setting teachers cannot change right now (locked by the administrator) keeps the activity's own value: the
    // form only shows the site's. Once unlocked, the activity has its own choice again.
    $current = $DB->get_record('pdfshield', ['id' => $data->id], 'watermark, pageimages', MUST_EXIST);
    foreach (shield::LOCKABLE as $name) {
        $data->$name = shield::fixed($name) ? (int) $current->$name : (empty($data->$name) ? 0 : 1);
    }
    $DB->update_record('pdfshield', $data);
    pdfshield_save_file($data);
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'pdfshield',
        $data->id,
        $data->completionexpected ?? null
    );
    return true;
}

/**
 * Deletes an activity and its files.
 *
 * @param int $id instance id
 * @return bool
 */
function pdfshield_delete_instance($id) {
    global $DB;
    if (!$instance = $DB->get_record('pdfshield', ['id' => $id])) {
        return false;
    }
    if ($cm = get_coursemodule_from_instance('pdfshield', $id)) {
        get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_pdfshield');
        \core_completion\api::update_completion_date_event($cm->id, 'pdfshield', $id, null);
    }
    $DB->delete_records('pdfshield', ['id' => $instance->id]);
    return true;
}

/**
 * Saves the PDF from the form's draft area, drops page images of a replaced PDF, and prepares the new pages in the
 * background when the activity shows pages as images.
 *
 * @param stdClass $data form data with coursemodule, id and files (draft item id)
 */
function pdfshield_save_file(stdClass $data): void {
    global $DB;
    $context = context_module::instance($data->coursemodule);
    if (!empty($data->files)) {
        file_save_draft_area_files(
            $data->files,
            $context->id,
            'mod_pdfshield',
            'content',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }
    $file = shield::main_file($context);
    shield::delete_old_pages($context, $file);
    $instance = $DB->get_record('pdfshield', ['id' => $data->id], '*', MUST_EXIST);
    if ($file && $instance->contenthash !== $file->get_contenthash()) {
        $DB->update_record('pdfshield', (object) ['id' => $instance->id, 'contenthash' => '', 'pagecount' => 0]);
    }
    if ($file && shield::effective($instance, 'pageimages') && shield::ghostscript_available()) {
        $task = new \mod_pdfshield\task\render_pages();
        $task->set_custom_data(['cmid' => (int) $data->coursemodule]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}

/**
 * Course page information: name and, when shown, the description.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 */
function pdfshield_get_coursemodule_info($coursemodule) {
    global $DB;
    if (!$instance = $DB->get_record('pdfshield', ['id' => $coursemodule->instance], 'id, name, intro, introformat')) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $instance->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('pdfshield', $instance, $coursemodule->id, false);
    }
    return $info;
}

/**
 * Marks the activity viewed (completion) and logs the view.
 *
 * @param stdClass $instance
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param context_module $context
 */
function pdfshield_view($instance, $course, $cm, $context) {
    $event = \mod_pdfshield\event\course_module_viewed::create(['context' => $context, 'objectid' => $instance->id]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('pdfshield', $instance);
    $event->trigger();
    // Only acts when completion is "view to complete": a learner's manual choice is never overridden.
    (new completion_info($course))->set_module_viewed($cm);
}

/**
 * File areas teachers can browse.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @return array
 */
function pdfshield_get_file_areas($course, $cm, $context) {
    return ['content' => get_string('files', 'mod_pdfshield')];
}

/**
 * Serves the PDF itself, to people who may edit the activity only (to check or replace their file). Readers never get
 * it from here: the viewer receives it through file.php, or only page images.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if not served
 */
function pdfshield_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'content') {
        return false;
    }
    require_course_login($course, true, $cm);
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return false;
    }
    array_shift($args); // Item id, always 0.
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'mod_pdfshield', 'content', 0, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}

/**
 * Accepts a PDF dropped on the course page.
 *
 * @return array
 */
function pdfshield_dndupload_register() {
    return ['files' => [['extension' => 'pdf', 'message' => get_string('dnduploadpdfshield', 'mod_pdfshield')]]];
}

/**
 * Creates an activity from a dropped PDF, with the site's default protection.
 *
 * @param stdClass $uploadinfo
 * @return int instance id
 */
function pdfshield_dndupload_handle($uploadinfo) {
    global $USER;
    // The same check as the form: only a real PDF (the extension alone proves nothing).
    $draft = get_file_storage()->get_area_files(
        context_user::instance($USER->id)->id,
        'user',
        'draft',
        $uploadinfo->draftitemid,
        'id',
        false
    );
    if (!$draft || !shield::is_pdf(reset($draft))) {
        throw new moodle_exception('notpdf', 'mod_pdfshield');
    }
    $data = new stdClass();
    $data->course = $uploadinfo->course->id;
    $data->name = $uploadinfo->displayname;
    $data->intro = '';
    $data->introformat = FORMAT_HTML;
    $data->coursemodule = $uploadinfo->coursemodule;
    $data->files = $uploadinfo->draftitemid;
    $data->watermark = (int) get_config('mod_pdfshield', 'watermark');
    $data->pageimages = (int) get_config('mod_pdfshield', 'pageimages');
    return pdfshield_add_instance($data);
}

/**
 * Page types for block placement.
 *
 * @param string $pagetype
 * @param stdClass $parentcontext
 * @param stdClass $currentcontext
 * @return array
 */
function pdfshield_page_type_list($pagetype, $parentcontext, $currentcontext) {
    return ['mod-pdfshield-*' => get_string('page-mod-pdfshield-x', 'mod_pdfshield')];
}

/**
 * Action for the "expected completion" event in the calendar and Timeline.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_pdfshield_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    $userid = 0
) {
    global $USER;
    $userid = $userid ?: $USER->id;
    $cm = get_fast_modinfo($event->courseid, $userid)->instances['pdfshield'][$event->instance];
    if (!$cm->uservisible) {
        return null;
    }
    $completion = new completion_info($cm->get_course());
    $data = $completion->get_data($cm, false, $userid);
    if ($data->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }
    return $factory->create_instance(
        get_string('view'),
        new moodle_url('/mod/pdfshield/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Nothing to reset: the activity keeps no user data.
 *
 * @param stdClass $data
 * @return array
 */
function pdfshield_reset_userdata($data) {
    return [];
}
