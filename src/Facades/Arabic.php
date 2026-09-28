<?php

namespace MostafaMoknaa\ArabicPdf\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string fix(string $text, ?int $maxCharsPerLine = null, ?string $direction = null)
 * @method static string fixForHtml(string $text, ?int $maxCharsPerLine = null, ?string $direction = null)
 * @method static string html(string $html)
 * @method static string shape(string $text)
 * @method static string reorder(string $line, string $direction = 'rtl')
 * @method static bool containsArabic(string $text)
 * @method static bool needsFixing(string $text)
 * @method static array wrap(string $text, int $maxChars)
 *
 * @see \MostafaMoknaa\ArabicPdf\Arabic
 */
class Arabic extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \MostafaMoknaa\ArabicPdf\Arabic::class;
    }
}
