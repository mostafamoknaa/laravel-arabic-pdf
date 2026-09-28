<?php

namespace MostafaMoknaa\ArabicPdf\Tests;

use MostafaMoknaa\ArabicPdf\Arabic;
use MostafaMoknaa\ArabicPdf\Text\Bidi;
use MostafaMoknaa\ArabicPdf\Text\Shaper;
use PHPUnit\Framework\TestCase;

class ArabicTest extends TestCase
{
    public function test_fix_shapes_and_reorders(): void
    {
        $arabic = new Arabic;

        $this->assertSame(
            (new Bidi)->reorder((new Shaper)->shape('السعر 100 جنيه'), Bidi::RTL),
            $arabic->fix('السعر 100 جنيه'),
        );
    }

    public function test_fix_is_idempotent(): void
    {
        $arabic = new Arabic;
        $fixed = $arabic->fix('مرحبا بكم في Laravel');

        $this->assertSame($fixed, $arabic->fix($fixed));
    }

    public function test_text_without_arabic_is_untouched(): void
    {
        $this->assertSame('Invoice #15 (paid)', (new Arabic)->fix('Invoice #15 (paid)'));
    }

    public function test_wrapped_lines_keep_reading_order(): void
    {
        $arabic = new Arabic;
        $lines = explode("\n", $arabic->fix('كلمة اولى ثانية ثالثة رابعة', 12));

        $this->assertSame([
            $arabic->fix('كلمة اولى'),
            $arabic->fix('ثانية ثالثة'),
            $arabic->fix('رابعة'),
        ], $lines);
    }

    public function test_each_paragraph_is_fixed_separately(): void
    {
        $arabic = new Arabic;

        $this->assertSame($arabic->fix('سطر اول')."\n".$arabic->fix('سطر ثاني'), $arabic->fix("سطر اول\nسطر ثاني"));
    }

    public function test_fix_for_html_escapes_and_uses_br(): void
    {
        $arabic = new Arabic;

        $this->assertSame(
            $arabic->fix('اول').'<br>&lt;b&gt; '.$arabic->fix('ثاني'),
            $arabic->fixForHtml("اول\n<b> ثاني"),
        );
    }

    public function test_it_can_strip_harakat(): void
    {
        $this->assertSame((new Arabic)->fix('محمد'), (new Arabic(['strip_harakat' => true]))->fix('مُحَمَّد'));
    }

    public function test_contains_arabic(): void
    {
        $arabic = new Arabic;

        $this->assertTrue($arabic->containsArabic('Hello مرحبا'));
        $this->assertFalse($arabic->containsArabic('Hello'));
    }
}
