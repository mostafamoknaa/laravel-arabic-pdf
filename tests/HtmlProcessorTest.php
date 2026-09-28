<?php

namespace MostafaMoknaa\ArabicPdf\Tests;

use MostafaMoknaa\ArabicPdf\Arabic;
use PHPUnit\Framework\TestCase;

class HtmlProcessorTest extends TestCase
{
    private Arabic $arabic;

    protected function setUp(): void
    {
        $this->arabic = new Arabic(['rtl_css' => false]);
    }

    public function test_it_fixes_text_nodes(): void
    {
        $this->assertSame('<p>'.$this->arabic->fix('مرحبا بكم').'</p>', $this->arabic->html('<p>مرحبا بكم</p>'));
    }

    public function test_inline_elements_are_reordered_in_rtl_paragraphs(): void
    {
        $html = $this->arabic->html('<p>السعر <b>100</b> جنيه</p>');

        $this->assertSame('<p>'.$this->arabic->fix(' جنيه').'<b>100</b>'.$this->arabic->fix('السعر ').'</p>', $html);
    }

    public function test_english_siblings_keep_their_order_in_rtl_paragraphs(): void
    {
        $html = $this->arabic->html('<p dir="rtl">مرحبا <b>Hello</b> <i>World</i></p>');

        $this->assertSame('<p dir="rtl"><b>Hello</b> <i>World</i>'.$this->arabic->fix('مرحبا ').'</p>', $html);
    }

    public function test_ltr_paragraphs_keep_element_order(): void
    {
        $html = $this->arabic->html('<p>Name: <b>محمد</b></p>');

        $this->assertSame('<p>Name: <b>'.$this->arabic->fix('محمد').'</b></p>', $html);
    }

    public function test_table_columns_are_reversed_inside_rtl(): void
    {
        $html = $this->arabic->html('<table dir="rtl"><tr><td>1</td><td>2</td><td>3</td></tr></table>');

        $this->assertSame('<table dir="rtl"><tr><td>3</td><td>2</td><td>1</td></tr></table>', $html);
    }

    public function test_table_columns_are_kept_when_disabled(): void
    {
        $arabic = new Arabic(['rtl_css' => false, 'reverse_table_columns' => false]);
        $html = '<table dir="rtl"><tr><td>1</td><td>2</td></tr></table>';

        $this->assertSame($html, $arabic->html($html));
    }

    public function test_line_breaks_are_boundaries(): void
    {
        $html = $this->arabic->html('<p>اول<br>ثاني</p>');

        $this->assertSame('<p>'.$this->arabic->fix('اول').'<br>'.$this->arabic->fix('ثاني').'</p>', $html);
    }

    public function test_wrap_attribute_splits_long_text(): void
    {
        $html = $this->arabic->html('<p data-arabic-wrap="12">كلمة اولى ثانية ثالثة</p>');

        $this->assertSame(
            '<p data-arabic-wrap="12">'.$this->arabic->fix('كلمة اولى').'<br>'.$this->arabic->fix('ثانية ثالثة').'</p>',
            $html,
        );
    }

    public function test_skip_attribute_and_scripts_are_untouched(): void
    {
        $html = '<div data-arabic-skip="">مرحبا</div><script>var a = "مرحبا";</script>';

        $this->assertSame($html, $this->arabic->html($html));
    }

    public function test_full_documents_keep_their_structure_and_get_rtl_css(): void
    {
        $arabic = new Arabic;
        $html = $arabic->html('<!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"></head><body><h1>عنوان</h1></body></html>');

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertMatchesRegularExpression('#<head><style>\[dir="rtl"\].*</style><meta charset="utf-8"></head><body>#', $html);
        $this->assertStringContainsString('<h1>'.$arabic->fix('عنوان').'</h1>', $html);
    }

    public function test_entities_and_special_characters_survive(): void
    {
        $html = $this->arabic->html('<p>Tom &amp; Jerry &lt;3 &copy;</p>');

        $this->assertStringContainsString('Tom &amp; Jerry &lt;3', $html);
    }

    public function test_processing_twice_does_not_break_the_text(): void
    {
        $once = $this->arabic->html('<p>مرحبا بكم</p>');

        $this->assertSame($once, $this->arabic->html($once));
    }
}
