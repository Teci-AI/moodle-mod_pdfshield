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

namespace mod_pdfshield;

use mod_pdfshield\local\shield;

/**
 * Settings, the PDF check, page images (Ghostscript) and the watermark.
 *
 * @package    mod_pdfshield
 * @category   test
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_pdfshield\local\shield
 */
final class shield_test extends \advanced_testcase {
    /**
     * An activity with a PDF of the given content (or a 3-page PDF).
     *
     * @param array $record
     * @return array [instance record, module context, its PDF]
     */
    private function activity(array $record = []): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $act = $this->getDataGenerator()->create_module('pdfshield', ['course' => $course->id] + $record);
        $context = \context_module::instance($act->cmid);
        return [$DB->get_record('pdfshield', ['id' => $act->id]), $context, shield::main_file($context)];
    }

    /**
     * Skips a test that needs Ghostscript when the site has none.
     */
    private function require_ghostscript(): void {
        if (!shield::ghostscript_available()) {
            $this->markTestSkipped('Ghostscript is not set up on this site (Path to ghostscript).');
        }
    }

    /**
     * The activity's own value applies, unless the administrator locked the setting; then the site's value does.
     */
    public function test_effective_settings(): void {
        [$instance] = $this->activity(['watermark' => 0]);
        set_config('watermark', 1, 'mod_pdfshield');
        $this->assertFalse(shield::effective($instance, 'watermark'));
        set_config('watermark_locked', 1, 'mod_pdfshield');
        $this->assertTrue(shield::effective($instance, 'watermark'));
        $this->assertTrue(shield::fixed('watermark'));
        set_config('watermark', 0, 'mod_pdfshield');
        $instance->watermark = 1;
        $this->assertFalse(shield::effective($instance, 'watermark'));
        set_config('watermark_locked', 0, 'mod_pdfshield');
        $this->assertTrue(shield::effective($instance, 'watermark'));
        $this->assertFalse(shield::fixed('watermark'));
    }

    /**
     * Page images cannot be chosen without Ghostscript.
     */
    public function test_page_images_need_ghostscript(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->pathtogs = '';
        $this->assertFalse(shield::ghostscript_available());
        $this->assertTrue(shield::fixed('pageimages'));
        $this->assertFalse(shield::fixed('watermark'));
    }

    /**
     * Only a file that starts with "%PDF-" counts as a PDF (Ghostscript would run anything else as PostScript).
     */
    public function test_is_pdf(): void {
        [, $context, $file] = $this->activity();
        $this->assertTrue(shield::is_pdf($file));
        $fs = get_file_storage();
        foreach (["%%PDF-1.7\n%!PS\nshowpage\n", " %PDF-1.7\n", "%!PS-Adobe-3.0\n%PDF-\n", ''] as $i => $content) {
            $other = $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_pdfshield',
                'filearea' => 'test', 'itemid' => 0, 'filepath' => '/', 'filename' => "f{$i}.pdf"], $content);
            $this->assertFalse(shield::is_pdf($other), json_encode($content));
        }
    }

    /**
     * Pages are counted and rendered once, kept per PDF and resolution.
     */
    public function test_page_images(): void {
        $this->require_ghostscript();
        [$instance, $context, $file] = $this->activity();
        $this->assertSame(3, shield::page_count($instance, $file));

        $image = shield::page_image($context, $file, 2);
        $this->assertNotNull($image);
        $info = getimagesizefromstring($image->get_content());
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertEqualsWithDelta(1240, $info[0], 2, 'A4 at 150 dpi');
        $this->assertSame((int) $image->get_id(), (int) shield::page_image($context, $file, 2)->get_id(), 'kept');
        $this->assertSame(2, shield::render_all_pages($context, $file));
        $this->assertNull(shield::page_image($context, $file, 9));

        set_config('imageresolution', 100, 'mod_pdfshield');
        $this->assertNotNull(shield::page_image($context, $file, 1));
        shield::delete_old_pages($context, $file);
        $paths = array_map(
            fn($f) => $f->get_filepath() . $f->get_filename(),
            array_values(get_file_storage()->get_area_files($context->id, 'mod_pdfshield', 'pages', 0, 'id', false))
        );
        $this->assertSame(['/' . $file->get_contenthash() . '-100/page-1.jpg'], $paths);
    }

    /**
     * A disguised PostScript file never reaches Ghostscript: no pages, and the failure is kept.
     */
    public function test_not_a_pdf_never_reaches_ghostscript(): void {
        global $DB;
        $this->require_ghostscript();
        $polyglot = "%%PDF-1.7\n%!PS\nnewpath 100 100 moveto 400 400 lineto 20 setlinewidth stroke showpage\n";
        [$instance, $context, $file] = $this->activity(['pageimages' => 1, 'pdf' => $polyglot]);
        $this->assertSame(0, shield::page_count($instance, $file));
        $this->assertEquals(-1, $DB->get_field('pdfshield', 'pagecount', ['id' => $instance->id]));
        $this->assertSame(0, shield::render_all_pages($context, $file));
        $this->assertNull(shield::page_image($context, $file, 1));
        $this->assertTrue(get_file_storage()->file_exists(
            $context->id,
            'mod_pdfshield',
            'pages',
            0,
            '/' . $file->get_contenthash() . '-150/',
            'page-1.failed'
        ));
    }

    /**
     * The watermark is drawn faintly across an image, which keeps its size.
     */
    public function test_watermark_image(): void {
        $this->resetAfterTest();
        $blank = imagecreatetruecolor(800, 1100);
        imagefill($blank, 0, 0, imagecolorallocate($blank, 255, 255, 255));
        ob_start();
        imagejpeg($blank, null, 90);
        $jpeg = ob_get_clean();
        $marked = shield::watermark_image($jpeg, 'Sam Student · 29 October 2026');
        $info = getimagesizefromstring($marked);
        $this->assertSame([800, 1100, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
        $faint = 0;
        $darkest = 255;
        $im = imagecreatefromstring($marked);
        for ($y = 0; $y < 1100; $y += 2) {
            for ($x = 0; $x < 800; $x += 2) {
                $r = (imagecolorat($im, $x, $y) >> 16) & 255;
                $faint += ($r < 250) ? 1 : 0;
                $darkest = min($darkest, $r);
            }
        }
        $this->assertGreaterThan(500, $faint, 'drawn across the page');
        $this->assertGreaterThan(200, $darkest, 'faint: the page stays readable');
    }
}
