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

namespace mod_pdfshield\local;

use context_module;
use stdClass;
use stored_file;

/**
 * What PDF Shield shows and how: effective settings, the activity's PDF, page images (Ghostscript), the watermark and
 * the checks every request from the viewer goes through.
 *
 * @package    mod_pdfshield
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shield {

    /** Settings an activity has, whose site value wins when the administrator locks it. */
    public const LOCKABLE = ['watermark', 'pageimages'];

    /** Header the viewer sends with every request: a page typed in the address bar or linked from elsewhere has none. */
    public const REQUEST_HEADER = 'X-PDFShield';

    /** Default page image resolution (dots per inch). */
    public const DEFAULT_RESOLUTION = 150;

    /** Most pages a document may have to be shown as images. */
    public const MAX_PAGES = 2000;

    /** Largest page image, in pixels (about A0 at 200 dpi); a bigger page is not shown. */
    public const MAX_PIXELS = 40000000;

    /** Seconds Ghostscript may take to count the pages. */
    private const TIMEOUT_COUNT = 30;

    /** Seconds Ghostscript may take to render one page. */
    private const TIMEOUT_PAGE = 60;

    /** Seconds Ghostscript may take to render every page (background task). */
    private const TIMEOUT_ALL = 600;

    /**
     * The value that applies to an activity: the site's when the administrator locked the setting, else the activity's.
     *
     * @param stdClass $instance the pdfshield record
     * @param string $name watermark or pageimages
     * @return bool
     */
    public static function effective(stdClass $instance, string $name): bool {
        $config = get_config('mod_pdfshield');
        if (!empty($config->{$name . '_locked'})) {
            return !empty($config->$name);
        }
        return !empty($instance->$name);
    }

    /**
     * Whether a setting is locked by the administrator.
     *
     * @param string $name watermark or pageimages
     * @return bool
     */
    public static function locked(string $name): bool {
        return !empty(get_config('mod_pdfshield', $name . '_locked'));
    }

    /**
     * Whether text search is allowed (site setting; never with page images).
     *
     * @return bool
     */
    public static function search_allowed(): bool {
        return !empty(get_config('mod_pdfshield', 'allowsearch'));
    }

    /**
     * Whether Ghostscript is set up, so pages can be shown as images.
     *
     * @return bool
     */
    public static function ghostscript_available(): bool {
        global $CFG;
        return !empty($CFG->pathtogs) && is_file($CFG->pathtogs) && is_executable($CFG->pathtogs)
            && function_exists('proc_open');
    }

    /**
     * Whether a file is a PDF: it starts with "%PDF-" at its very first byte. Ghostscript decides how to read a file
     * from its first bytes, so only such a file is ever given to it (anything else could be run as PostScript).
     *
     * @param stored_file $file
     * @return bool
     */
    public static function is_pdf(stored_file $file): bool {
        $handle = $file->get_content_file_handle();
        if (!$handle) {
            return false;
        }
        $start = fread($handle, 5);
        fclose($handle);
        return $start === '%PDF-';
    }

    /**
     * The activity's PDF.
     *
     * @param context_module $context
     * @return stored_file|null
     */
    public static function main_file(context_module $context): ?stored_file {
        $files = get_file_storage()->get_area_files($context->id, 'mod_pdfshield', 'content', 0, 'sortorder DESC, id ASC',
            false);
        return $files ? reset($files) : null;
    }

    /**
     * The text drawn across every page for the current user: their name and today's date.
     *
     * @return string
     */
    public static function watermark_text(): string {
        global $USER;
        return fullname($USER) . ' · ' . userdate(time(), get_string('strftimedate', 'langconfig'));
    }

    /**
     * Page image resolution from the site setting.
     *
     * @return int
     */
    public static function resolution(): int {
        $dpi = (int) get_config('mod_pdfshield', 'imageresolution');
        return in_array($dpi, [100, 150, 200], true) ? $dpi : self::DEFAULT_RESOLUTION;
    }

    /**
     * Number of pages of the activity's PDF, counted once with Ghostscript and kept in the activity record.
     *
     * @param stdClass $instance the pdfshield record (updated when counted)
     * @param stored_file $file the activity's PDF
     * @return int 0 when it cannot be counted, is not a PDF, or has more than MAX_PAGES pages (kept as -1, so it is not
     *     tried again for the same PDF)
     */
    public static function page_count(stdClass $instance, stored_file $file): int {
        global $DB;
        if ($instance->contenthash === $file->get_contenthash() && $instance->pagecount != 0) {
            return max(0, (int) $instance->pagecount);
        }
        if (!self::ghostscript_available()) {
            return 0;
        }
        $pdf = self::temp_copy($file);
        $count = $pdf ? self::ghostscript_page_count($pdf) : 0;
        if ($pdf) {
            @unlink($pdf);
        }
        $stored = ($count > 0 && $count <= self::MAX_PAGES) ? $count : -1;
        $instance->contenthash = $file->get_contenthash();
        $instance->pagecount = $stored;
        $DB->update_record('pdfshield', (object) ['id' => $instance->id, 'contenthash' => $instance->contenthash,
            'pagecount' => $stored]);
        return max(0, $stored);
    }

    /**
     * One page as a JPEG image, rendered by Ghostscript the first time and kept in Moodle's file storage (file area
     * "pages", one folder per PDF content and resolution, so a new PDF or resolution gets new images).
     *
     * @param context_module $context
     * @param stored_file $file the activity's PDF
     * @param int $page 1-based
     * @return stored_file|null null when it cannot be rendered (a page that failed is not tried again for the same
     *     PDF), or when another request is rendering it and takes too long
     */
    public static function page_image(context_module $context, stored_file $file, int $page): ?stored_file {
        $fs = get_file_storage();
        $path = self::pages_path($file);
        $name = "page-{$page}.jpg";
        $failed = "page-{$page}.failed";
        if ($image = $fs->get_file($context->id, 'mod_pdfshield', 'pages', 0, $path, $name)) {
            return $image;
        }
        if (!self::ghostscript_available() || $fs->file_exists($context->id, 'mod_pdfshield', 'pages', 0, $path, $failed)) {
            return null;
        }
        // Several readers may open the same page at once: render it once; without the lock, do not render at all.
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_pdfshield');
        $lock = $lockfactory->get_lock("page-{$context->id}-" . $file->get_contenthash() . "-{$page}",
            self::TIMEOUT_PAGE + 10);
        if (!$lock) {
            return $fs->get_file($context->id, 'mod_pdfshield', 'pages', 0, $path, $name) ?: null;
        }
        $pdf = null;
        try {
            if ($image = $fs->get_file($context->id, 'mod_pdfshield', 'pages', 0, $path, $name)) {
                return $image;
            }
            $pdf = self::temp_copy($file);
            $out = $pdf ? dirname($pdf) . '/page.jpg' : '';
            $ok = $pdf && self::ghostscript_render($pdf, self::TIMEOUT_PAGE, "-dFirstPage={$page}", "-dLastPage={$page}",
                '-sOutputFile=' . $out) && self::acceptable_image($out);
            $image = $ok ? self::store_page($context, $path, $name, $out) : null;
            if (!$image) {
                self::store_page($context, $path, $failed, null);
            }
            return $image;
        } finally {
            if ($pdf) {
                @unlink(dirname($pdf) . '/page.jpg');
                @unlink($pdf);
                @rmdir(dirname($pdf));
            }
            $lock->release();
        }
    }

    /**
     * Renders every page not rendered yet (one Ghostscript run), for the background task after a PDF is saved.
     *
     * @param context_module $context
     * @param stored_file $file the activity's PDF
     * @return int pages rendered
     */
    public static function render_all_pages(context_module $context, stored_file $file): int {
        if (!self::ghostscript_available()) {
            return 0;
        }
        $fs = get_file_storage();
        $path = self::pages_path($file);
        $pdf = self::temp_copy($file);
        if (!$pdf) {
            return 0;
        }
        $dir = dirname($pdf);
        $rendered = 0;
        try {
            // Pages written before a timeout are kept; the others are rendered when first shown.
            self::ghostscript_render($pdf, self::TIMEOUT_ALL, '-sOutputFile=' . $dir . '/page-%d.jpg');
            foreach (glob("{$dir}/page-*.jpg") as $out) {
                $name = basename($out);
                if (self::acceptable_image($out)
                        && !$fs->file_exists($context->id, 'mod_pdfshield', 'pages', 0, $path, $name)
                        && self::store_page($context, $path, $name, $out)) {
                    $rendered++;
                }
                @unlink($out);
            }
        } finally {
            @unlink($pdf);
            @rmdir($dir);
        }
        return $rendered;
    }

    /**
     * Deletes page images of other PDFs or resolutions than the current ones.
     *
     * @param context_module $context
     * @param stored_file|null $file the activity's PDF, or null to delete all page images
     */
    public static function delete_old_pages(context_module $context, ?stored_file $file): void {
        $fs = get_file_storage();
        $keep = $file ? self::pages_path($file) : null;
        foreach ($fs->get_area_files($context->id, 'mod_pdfshield', 'pages', 0, 'id', false) as $image) {
            if ($image->get_filepath() !== $keep) {
                $image->delete();
            }
        }
    }

    /**
     * Draws the watermark text across a JPEG image, diagonally and faintly, the way the viewer does on PDF pages.
     *
     * @param string $jpeg image data
     * @param string $text
     * @return string JPEG data (the original when the image cannot be decoded)
     */
    public static function watermark_image(string $jpeg, string $text): string {
        global $CFG;
        $image = @imagecreatefromstring($jpeg);
        if (!$image) {
            return $jpeg;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $font = $CFG->libdir . '/default.ttf';
        $size = max(10, (int) round($width / 42));
        // Alpha 0 is opaque, 127 transparent: faint enough to read the page, clear enough to read the name.
        $colour = imagecolorallocatealpha($image, 90, 90, 90, 98);
        $angle = 30;
        if (is_readable($font) && function_exists('imagettftext')) {
            $box = imagettfbbox($size, 0, $font, $text);
            $textwidth = abs($box[2] - $box[0]);
            $stepx = $textwidth + $size * 4;
            $stepy = $size * 7;
            for ($y = -$height; $y < $height * 2; $y += $stepy) {
                for ($x = -$width; $x < $width * 2; $x += $stepx) {
                    imagettftext($image, $size, $angle, (int) $x, (int) $y, $colour, $font, $text);
                }
            }
        } else {
            for ($y = 20; $y < $height; $y += 80) {
                imagestring($image, 5, 20, $y, $text, $colour);
            }
        }
        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);
        return ob_get_clean();
    }

    /**
     * Stops any request that does not come from the viewer on the activity page: it must be a POST with the session
     * key and the viewer's header, so a copied address does not work in another browser, after logout, or as a link.
     */
    public static function require_viewer_request(): void {
        $header = 'HTTP_' . strtoupper(str_replace('-', '_', self::REQUEST_HEADER));
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SERVER[$header])) {
            self::refuse(400);
        }
        if (!confirm_sesskey()) {
            self::refuse(403);
        }
    }

    /**
     * Ends the request with an empty answer and the given status.
     *
     * @param int $status
     */
    public static function refuse(int $status): void {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        exit;
    }

    /**
     * Headers for anything the viewer receives: never cached, never shown or saved as a file by the browser itself.
     *
     * @param string $type content type
     * @param int $length
     */
    public static function send_private_headers(string $type, int $length): void {
        header('Content-Type: ' . $type);
        header('Content-Length: ' . $length);
        header('Content-Disposition: attachment; filename="document.bin"');
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Folder of the page images of a PDF at the current resolution.
     *
     * @param stored_file $file
     * @return string
     */
    private static function pages_path(stored_file $file): string {
        return '/' . $file->get_contenthash() . '-' . self::resolution() . '/';
    }

    /**
     * File record of a page image.
     *
     * @param context_module $context
     * @param string $path
     * @param string $name
     * @return array
     */
    private static function page_record(context_module $context, string $path, string $name): array {
        return ['contextid' => $context->id, 'component' => 'mod_pdfshield', 'filearea' => 'pages', 'itemid' => 0,
            'filepath' => $path, 'filename' => $name, 'mimetype' => 'image/jpeg'];
    }

    /**
     * Stores a page image (or, with no file, the marker of a page that could not be rendered). Another request may have
     * stored it meanwhile: that one is returned.
     *
     * @param context_module $context
     * @param string $path
     * @param string $name
     * @param string|null $out the rendered image, or null for a failure marker
     * @return stored_file|null
     */
    private static function store_page(context_module $context, string $path, string $name, ?string $out): ?stored_file {
        $fs = get_file_storage();
        $record = self::page_record($context, $path, $name);
        try {
            if ($out === null) {
                return $fs->create_file_from_string(['mimetype' => 'text/plain'] + $record, '');
            }
            return $fs->create_file_from_pathname($record, $out);
        } catch (\stored_file_creation_exception $e) {
            return $fs->get_file($context->id, 'mod_pdfshield', 'pages', 0, $path, $name) ?: null;
        }
    }

    /**
     * Whether a rendered page can be kept: a JPEG, not empty, not bigger than MAX_PIXELS (decoding a huge image for
     * the watermark on every request would exhaust the server's memory).
     *
     * @param string $out
     * @return bool
     */
    private static function acceptable_image(string $out): bool {
        $info = (is_file($out) && filesize($out)) ? @getimagesize($out) : false;
        return $info && $info[2] === IMAGETYPE_JPEG && $info[0] * $info[1] <= self::MAX_PIXELS;
    }

    /**
     * A temporary copy of the PDF for Ghostscript, in its own folder (works with any file storage, local or object
     * storage). Only for a real PDF (see is_pdf).
     *
     * @param stored_file $file
     * @return string|null path, or null when the file is not a PDF
     */
    private static function temp_copy(stored_file $file): ?string {
        if (!self::is_pdf($file)) {
            return null;
        }
        $path = make_request_directory() . '/document.pdf';
        $file->copy_content_to($path);
        return $path;
    }

    /**
     * Runs Ghostscript to make JPEG page images, in safe mode (the PDF cannot read or write other files).
     *
     * @param string $pdf
     * @param int $timeout seconds
     * @param string ...$args output and page range arguments
     * @return bool whether it finished without error
     */
    private static function ghostscript_render(string $pdf, int $timeout, string ...$args): bool {
        global $CFG;
        [$status, $output] = self::run(array_merge([$CFG->pathtogs, '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE',
            '-sDEVICE=jpeg', '-dJPEGQ=85', '-r' . self::resolution(), '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4'],
            $args, ['-f', $pdf]), $timeout);
        if ($status !== 0) {
            debugging('PDF Shield: Ghostscript could not render the PDF (' . $status . '): ' . substr($output, 0, 300),
                DEBUG_DEVELOPER);
        }
        return $status === 0;
    }

    /**
     * Counts the pages of a PDF with Ghostscript.
     *
     * @param string $pdf
     * @return int 0 when it cannot be counted
     */
    private static function ghostscript_page_count(string $pdf): int {
        global $CFG;
        // A PostScript string: forward slashes work on every platform, and ( ) \ must be escaped.
        $psname = addcslashes(str_replace('\\', '/', $pdf), '()\\');
        [$status, $output] = self::run([$CFG->pathtogs, '-q', '-dNODISPLAY', '-dSAFER', '--permit-file-read=' . $pdf,
            '-c', "({$psname}) (r) file runpdfbegin pdfpagecount = quit"], self::TIMEOUT_COUNT);
        $lines = preg_split('/\R/', trim($output));
        $last = trim((string) end($lines));
        return ($status === 0 && ctype_digit($last)) ? (int) $last : 0;
    }

    /**
     * Runs a program without a shell (its arguments are passed as they are, on every platform), stopping it after
     * the time limit.
     *
     * @param array $command program and arguments
     * @param int $timeout seconds
     * @return array [exit status (-1 when stopped or not started), output and errors]
     */
    private static function run(array $command, int $timeout): array {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return [-1, ''];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $deadline = microtime(true) + $timeout;
        while (true) {
            $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                $code = $status['exitcode'];
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                $code = -1;
                break;
            }
            usleep(20000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return [$code, $output];
    }
}
