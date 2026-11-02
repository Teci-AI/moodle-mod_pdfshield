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
 * PDF Shield's loader for PDF.js (not part of PDF.js): the viewer (amd/src/viewer.js) adds this file to the page as a
 * module script. It imports PDF.js as the ES module it is and hands it over, so the viewer itself has no import(),
 * which Moodle's build tool would turn into a RequireJS load.
 *
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import * as pdfjsLib from './pdf.min.js';

window.modPdfshieldPdfjs = pdfjsLib;
window.dispatchEvent(new CustomEvent('mod_pdfshield:pdfjs', {detail: pdfjsLib}));
