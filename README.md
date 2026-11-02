# PDF Shield (mod_pdfshield)

A Moodle activity that shows a PDF in the course page without a download, print or "open file" button.

- **The PDF is only sent to the viewer on the activity page**: a POST with the session key, to signed-in users who may see the activity (not guests, unless an administrator gives the guest role
  `mod/pdfshield:view`). A copied address does not work in another browser, after logging out, or as a link. The
  browser is told not to cache it. Teachers who can edit the activity still get their file as usual.
- **Watermark** (optional): the reader's name and the date faintly across every page, so a shared page can be traced.
- **Pages as images** (optional): the server turns each page into an image with Ghostscript and sends only the images.
  The PDF itself never reaches the browser; its text cannot be selected. The watermark is drawn into each image on the
  server.
- **Text search** (optional, site setting, off by default): matches are highlighted as boxes; the text itself is never
  put in the page, so it still cannot be selected or copied.
- Current PDF.js (6.4.299, `thirdparty/pdfjs`), which cannot run scripts from a PDF. No iframe, no separate viewer
  page, no login from links.
- Completion on view (a learner's manual choice is never overridden), expected completion date in the calendar and the
  Timeline, backup and restore, drag and drop of a PDF onto the course page, groupings, privacy provider (no personal
  data stored).

**What it cannot do:** nothing shown on a screen can be fully protected. Screenshots and photos of the screen are
always possible; the watermark is there to discourage sharing them. In PDF mode the file reaches the browser (that is
how it is drawn): any user who opens the browser's developer tools can save it, without the watermark, which the
viewer draws on screen. Use pages as images when that matters: then only watermarked page images reach the browser.
The pages are drawn as images, so screen readers cannot read the document's text.

**Ghostscript** reads the PDFs teachers upload (pages as images only). Only files that start as a PDF are given to it,
in its safe mode, without a shell, with a time limit and a size limit for page images. Keep Ghostscript up to date on
the server: like Moodle's own assignment annotation, the plugin relies on it to handle untrusted PDFs safely.

## Settings

Site administration > Plugins > Activity modules > PDF Shield:

| Setting | Default | |
|---|---|---|
| Watermark | off | Default for new activities. **Locked**: every activity follows the site value; teachers see their activity's own value, fixed, with a note saying what the site uses. Each activity keeps its own value, which applies again when unlocked. |
| Show pages as images | off | Same, with its own **Locked** box. Needs Ghostscript (Site administration > Server > System paths). Documents of up to 2,000 pages; page images are kept on the server (without the watermark, which is drawn when a page is sent). |
| Page image resolution | 150 dpi | 100, 150 or 200. |
| Allow text search | off | Never in page-image mode. |

When pages as images apply to an activity but Ghostscript is missing, the activity shows a notice instead of sending
the PDF.

## Licence

GNU GPL v3 or later (see `LICENSE`). PDF.js, in `thirdparty/pdfjs`, is under the Apache License 2.0
(declared in `thirdpartylibs.xml`).

## Requirements

Moodle 4.5 LTS to 5.3, PHP 8.1 or later, MySQL, MariaDB or PostgreSQL. For pages as images: Ghostscript (the same one Moodle uses to annotate
assignment PDFs) and PHP's GD extension (for the watermark on images).

## Development

- The viewer is `amd/src/viewer.js`; `amd/build/viewer.min.js` (and its map) is built by Moodle's grunt: with the
  plugin in a Moodle 5.x checkout that has its Node packages installed, run `npx grunt amd` in `mod/pdfshield`.
  PDF.js is loaded by `thirdparty/pdfjs/loader.js`, a module script, because grunt would turn an `import()` into a
  RequireJS load.
- `bin/build.sh` builds `build/mod_pdfshield-<version>.zip`.
- Releasing: raise `$plugin->version` and set `$plugin->release` in `version.php`, add the version's section to
  `CHANGES.md`, then `git tag v1.0.1 && git push origin v1.0.1`. GitHub Actions checks both, builds the zip and
  publishes the release with the notes from `CHANGES.md` and the zip attached, for upload to the moodle.org plugins
  directory.
- Updating PDF.js: see `thirdparty/pdfjs/readme_moodle.txt`.
