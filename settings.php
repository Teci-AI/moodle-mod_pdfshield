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
 * PDF Shield: site settings. Watermark and page images are defaults for new activities, each with a "Locked" box
 * that makes every activity follow the site value.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $setting = new admin_setting_configcheckbox('mod_pdfshield/watermark',
        new lang_string('settings_watermark', 'mod_pdfshield'),
        new lang_string('settings_watermark_desc', 'mod_pdfshield'), 0);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $description = get_string('settings_pageimages_desc', 'mod_pdfshield');
    if (!\mod_pdfshield\local\shield::ghostscript_available()) {
        $description .= '<br><strong>' . get_string('pageimagesunavailableadmin', 'mod_pdfshield') . '</strong>';
    }
    $setting = new admin_setting_configcheckbox('mod_pdfshield/pageimages',
        new lang_string('settings_pageimages', 'mod_pdfshield'), $description, 0);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $settings->add(new admin_setting_configselect('mod_pdfshield/imageresolution',
        new lang_string('settings_imageresolution', 'mod_pdfshield'),
        new lang_string('settings_imageresolution_desc', 'mod_pdfshield'),
        \mod_pdfshield\local\shield::DEFAULT_RESOLUTION, [100 => '100', 150 => '150', 200 => '200']));

    $settings->add(new admin_setting_configcheckbox('mod_pdfshield/allowsearch',
        new lang_string('settings_allowsearch', 'mod_pdfshield'),
        new lang_string('settings_allowsearch_desc', 'mod_pdfshield'), 0));
}
