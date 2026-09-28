<?php

namespace MostafaMoknaa\ArabicPdf\Facades;

use Illuminate\Support\Facades\Facade;
use MostafaMoknaa\ArabicPdf\PdfDocument;
use MostafaMoknaa\ArabicPdf\PdfFactory;

/**
 * @method static PdfDocument make()
 * @method static PdfDocument loadView(string $view, array $data = [], array $mergeData = [])
 * @method static PdfDocument loadHTML(string $html)
 * @method static PdfDocument loadFile(string $path)
 *
 * @see PdfFactory
 */
class ArabicPdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PdfFactory::class;
    }
}
