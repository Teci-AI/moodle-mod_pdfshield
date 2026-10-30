// PDF Shield's loader for PDF.js (not part of PDF.js): the viewer (amd/src/viewer.js) adds this file to the page as a
// module script. It imports PDF.js as the ES module it is and hands it over, so the viewer itself has no import(),
// which Moodle's build tool would turn into a RequireJS load.
import * as pdfjsLib from './pdf.min.js';

window.modPdfshieldPdfjs = pdfjsLib;
window.dispatchEvent(new CustomEvent('mod_pdfshield:pdfjs', {detail: pdfjsLib}));
