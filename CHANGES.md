# Changes

## 1.0.0 (2 November 2026)

First release.

- An activity that shows one PDF in the course page with its own viewer (PDF.js 6.4.299): page navigation, zoom,
  full screen. No download, print or "open file" control; printing the page shows a note instead of the document.
- The PDF is sent only to that viewer, for signed-in users who may see the activity; a copied address does not work
  elsewhere. Teachers who can edit the activity still get their file.
- Optional watermark: the reader's name and the date faintly across every page.
- Optional pages as images (Ghostscript): the PDF never reaches the browser; the watermark is drawn into each image.
- Site defaults for the watermark and page images, each with a Locked option that applies the site value to every
  activity while keeping each activity's own choice for when it is unlocked.
- Optional text search (site setting, off by default), with matches highlighted and the text never put in the page.
- Completion on view, expected completion date in the calendar and Timeline, backup and restore, drag and drop of a
  PDF onto the course page, privacy provider.
- Moodle 4.5 LTS to 5.3, PHP 8.1 or later.
