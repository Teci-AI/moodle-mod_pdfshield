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
 * PDF Shield: English strings.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['contentheader'] = 'PDF';
$string['dnduploadpdfshield'] = 'Add a PDF Shield activity';
$string['eventerror'] = 'The page could not be prepared.';
$string['filenotfound'] = 'This activity has no PDF yet.';
$string['files'] = 'PDF file';
$string['files_help'] = 'The PDF students read in the activity. Students can read it in the page, but there is no button to download or print it.';
$string['fullscreen'] = 'Full screen';
$string['loaderror'] = 'The document could not be shown. Reload the page to try again.';
$string['loading'] = 'Loading the document…';
$string['modulename'] = 'PDF Shield';
$string['modulename_help'] = 'Shows a PDF in the course page without a download or print button.

The PDF is only sent to signed-in users who may see the activity, never by a link that works on its own. Optionally, every page shows the reader\'s name and the date, and the pages can be shown as images so the PDF never leaves the server.

Nothing shown on a screen can be fully protected: screenshots and photos of the screen are always possible.';
$string['modulename_link'] = 'mod/pdfshield/view';
$string['modulenameplural'] = 'PDF Shields';
$string['nextmatch'] = 'Next match';
$string['nextpage'] = 'Next page';
$string['noprint'] = 'Printing is turned off for this document.';
$string['notpdf'] = 'This file is not a PDF.';
$string['page'] = 'Page';
$string['page-mod-pdfshield-x'] = 'Any PDF Shield activity page';
$string['pageimages'] = 'Show pages as images';
$string['pageimages_help'] = 'The server turns each page into an image and sends only the images: the PDF itself never reaches the browser, so it cannot be saved from the browser and its text cannot be selected. Text search is not available in this mode.';
$string['pageimagesneeded'] = 'This document cannot be shown right now.';
$string['pageimagesunavailable'] = 'Not available on this site: it needs Ghostscript on the server.';
$string['pageimagesunavailableadmin'] = 'Ghostscript is not set up on this server (Site administration > Server > System paths > Path to ghostscript), so pages cannot be shown as images.';
$string['pageof'] = 'of {$a}';
$string['pages'] = 'Document pages';
$string['pdfshield:addinstance'] = 'Add a new PDF Shield activity';
$string['pdfshield:view'] = 'View PDF Shield activities';
$string['pluginadministration'] = 'PDF Shield administration';
$string['pluginname'] = 'PDF Shield';
$string['previousmatch'] = 'Previous match';
$string['previouspage'] = 'Previous page';
$string['privacy:metadata'] = 'PDF Shield stores no personal data. The reader\'s name shown on pages is drawn when the page is shown and not stored.';
$string['protection'] = 'Protection';
$string['search'] = 'Find in document';
$string['searchnone'] = 'No matches';
$string['searchresult'] = 'Match {$a->current} of {$a->total}';
$string['sessionended'] = 'Your session has ended. Log in again to read this document.';
$string['setbysite'] = 'Set for the whole site by the administrator.';
$string['settings_allowsearch'] = 'Allow text search';
$string['settings_allowsearch_desc'] = 'Readers can search the text of a document and see the matches highlighted. Off by default. Search is never available when pages are shown as images.';
$string['settings_imageresolution'] = 'Page image resolution';
$string['settings_imageresolution_desc'] = 'Resolution of the page images, in dots per inch. Higher is sharper when zooming in, but larger to send.';
$string['settings_pageimages'] = 'Show pages as images';
$string['settings_pageimages_desc'] = 'Default for new activities. When locked, every activity follows this setting and teachers cannot change it. Needs Ghostscript (Site administration > Server > System paths).';
$string['settings_watermark'] = 'Watermark';
$string['settings_watermark_desc'] = 'Default for new activities. When locked, every activity follows this setting and teachers cannot change it.';
$string['toolbar'] = 'Document controls';
$string['watermark'] = 'Watermark';
$string['watermark_help'] = 'Shows the reader\'s name and today\'s date faintly across every page, so a page that is shared can be traced back.';
$string['zoomfit'] = 'Fit to width';
$string['zoomin'] = 'Zoom in';
$string['zoomout'] = 'Zoom out';
