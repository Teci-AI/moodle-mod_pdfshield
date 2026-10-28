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
 * PDF Shield: the activity form.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_pdfshield\local\shield;

/**
 * The activity form: name, description, one PDF, and the protection settings.
 */
class mod_pdfshield_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '48']);
        $mform->setType('name', empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'contentsection', get_string('contentheader', 'mod_pdfshield'));
        $mform->setExpanded('contentsection');
        $mform->addElement('filemanager', 'files', get_string('files', 'mod_pdfshield'), null, [
            'accepted_types' => ['.pdf'],
            'maxfiles' => 1,
            'subdirs' => 0,
            'maxbytes' => $this->get_course()->maxbytes ?? 0,
        ]);
        $mform->addHelpButton('files', 'files', 'mod_pdfshield');
        $mform->addRule('files', null, 'required', null, 'client');

        $mform->addElement('header', 'protection', get_string('protection', 'mod_pdfshield'));
        $mform->setExpanded('protection');
        $this->protection_setting('watermark', true);
        $this->protection_setting('pageimages', shield::ghostscript_available());

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * A protection checkbox: the site default for a new activity; fixed to the site value when the administrator
     * locked it; off and fixed when it cannot work on this site.
     *
     * @param string $name watermark or pageimages
     * @param bool $available
     */
    private function protection_setting(string $name, bool $available): void {
        $mform = $this->_form;
        $mform->addElement('advcheckbox', $name, get_string($name, 'mod_pdfshield'));
        $mform->addHelpButton($name, $name, 'mod_pdfshield');
        $mform->setDefault($name, (int) get_config('mod_pdfshield', $name));
        if (!$available) {
            $mform->setConstant($name, 0);
            $mform->freeze($name);
            $mform->addElement('static', $name . 'note', '', get_string('pageimagesunavailable', 'mod_pdfshield'));
        } else if (shield::locked($name)) {
            $mform->setConstant($name, (int) get_config('mod_pdfshield', $name));
            $mform->freeze($name);
            $mform->addElement('static', $name . 'note', '', get_string('setbysite', 'mod_pdfshield'));
        }
    }

    /**
     * Puts the activity's PDF in the form's draft area.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        $draftitemid = file_get_submitted_draft_itemid('files');
        file_prepare_draft_area($draftitemid, $this->context ? $this->context->id : null, 'mod_pdfshield', 'content', 0,
            ['subdirs' => 0, 'maxfiles' => 1]);
        $defaultvalues['files'] = $draftitemid;
    }

    /**
     * One file, and a real PDF.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);
        $usercontext = context_user::instance($USER->id);
        $draft = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $data['files'] ?? 0, 'id', false);
        if (!$draft) {
            $errors['files'] = get_string('required');
            return $errors;
        }
        $file = reset($draft);
        $handle = $file->get_content_file_handle();
        $start = $handle ? fread($handle, 1024) : '';
        if ($handle) {
            fclose($handle);
        }
        if (strpos((string) $start, '%PDF-') === false) {
            $errors['files'] = get_string('notpdf', 'mod_pdfshield');
        }
        return $errors;
    }
}
