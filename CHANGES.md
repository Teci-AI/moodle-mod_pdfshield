# Changes

## 1.0.2 (3 November 2026)

- Copyright lines now give the maintainer's website. No change in behaviour.

## 1.0.1 (2 November 2026)

- Adds `db/upgrade.php` (no upgrade steps yet), which the Moodle Marketplace requires in every activity plugin.
- The viewer's requests are checked with Moodle's own functions (a POST with the session key) instead of reading PHP's
  request data directly; a request without a session key is refused cleanly instead of showing an error page.
- Boilerplate and copyright in the PDF.js loader; the licence (GNU GPL v3 or later, PDF.js under Apache 2.0) stated in
  the README.
- Tested on MySQL 8.4 and MariaDB 11.4 as well as PostgreSQL, and with developer debugging on.

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
