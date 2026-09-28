<?php

namespace MostafaMoknaa\ArabicPdf\Tests;

use Illuminate\Support\Facades\Blade;
use MostafaMoknaa\ArabicPdf\ArabicPdfServiceProvider;
use MostafaMoknaa\ArabicPdf\Facades\Arabic;
use MostafaMoknaa\ArabicPdf\Facades\ArabicPdf;
use MostafaMoknaa\ArabicPdf\PdfDocument;
use Orchestra\Testbench\TestCase;

class LaravelTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ArabicPdfServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [__DIR__.'/fixtures/views']);
        $app['config']->set('arabic-pdf.dompdf.fontDir', sys_get_temp_dir().'/arabic-pdf-fonts');
        $app['config']->set('arabic-pdf.dompdf.fontCache', sys_get_temp_dir().'/arabic-pdf-fonts');
    }

    public function test_the_facades_are_registered(): void
    {
        $this->assertInstanceOf(PdfDocument::class, ArabicPdf::loadHTML('<p>مرحبا</p>'));
        $this->assertSame((new \MostafaMoknaa\ArabicPdf\Arabic)->fix('مرحبا'), Arabic::fix('مرحبا'));
    }

    public function test_it_renders_a_view_to_pdf(): void
    {
        $pdf = ArabicPdf::loadView('invoice', ['customer' => 'محمد']);

        $this->assertStringContainsString(Arabic::fix('محمد'), $pdf->html());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }

    public function test_download_and_stream_responses(): void
    {
        $pdf = ArabicPdf::loadHTML('<p>مرحبا</p>');

        $download = $pdf->download('فاتورة.pdf');
        $this->assertSame('application/pdf', $download->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString("filename*=UTF-8''".rawurlencode('فاتورة.pdf'), $download->headers->get('Content-Disposition'));

        $this->assertStringStartsWith('inline;', $pdf->stream()->headers->get('Content-Disposition'));
    }

    public function test_it_saves_to_disk(): void
    {
        $path = sys_get_temp_dir().'/arabic-pdf-tests/'.uniqid().'.pdf';

        ArabicPdf::loadHTML('<p>مرحبا</p>')->setPaper('a5', 'landscape')->save($path);

        $this->assertFileExists($path);
        $this->assertStringStartsWith('%PDF-', file_get_contents($path));
        unlink($path);
    }

    public function test_blade_directive(): void
    {
        $this->assertSame(Arabic::fixForHtml('مرحبا بكم'), Blade::render('@arabic($text)', ['text' => 'مرحبا بكم']));
    }
}
