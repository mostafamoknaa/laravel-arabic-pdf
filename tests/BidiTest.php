<?php

namespace MostafaMoknaa\ArabicPdf\Tests;

use MostafaMoknaa\ArabicPdf\Text\Bidi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BidiTest extends TestCase
{
    /**
     * Input and expected visual output use unshaped letters to keep them readable:
     * the visual string is what you get when reading it strictly left to right.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function lines(): array
    {
        return [
            'reverses arabic words' => ['ابت ثجح', Bidi::RTL, 'حجث تبا'],
            'keeps numbers left to right' => ['سعر 150 جنيه', Bidi::RTL, 'هينج 150 رعس'],
            'keeps decimal numbers together' => ['المبلغ 1,250.50', Bidi::RTL, '1,250.50 غلبملا'],
            'keeps percent with its number' => ['خصم 15%', Bidi::RTL, '15% مصخ'],
            'keeps english runs in order' => ['نص Laravel 11 هنا', Bidi::RTL, 'انه Laravel 11 صن'],
            'mirrors brackets' => ['(نص)', Bidi::RTL, '(صن)'],
            'arabic inside ltr text' => ['Hello سلام world', Bidi::LTR, 'Hello مالس world'],
            'plain ltr text is untouched' => ['Hello world 2026', Bidi::LTR, 'Hello world 2026'],
            'trailing space goes to the visual end' => ['نص ', Bidi::RTL, ' صن'],
        ];
    }

    #[DataProvider('lines')]
    public function test_it_reorders_to_visual_order(string $logical, string $base, string $visual): void
    {
        $this->assertSame($visual, (new Bidi)->reorder($logical, $base));
    }

    public function test_it_detects_the_direction_from_the_first_strong_character(): void
    {
        $this->assertSame(Bidi::RTL, Bidi::detectDirection('123 مرحبا Hello'));
        $this->assertSame(Bidi::LTR, Bidi::detectDirection('- Hello مرحبا'));
        $this->assertNull(Bidi::detectDirection('123 - 456'));
    }

    public function test_harakat_stay_attached_to_their_letter(): void
    {
        $this->assertSame("ب\u{064E}ا", (new Bidi)->reorder("اب\u{064E}", Bidi::RTL));
    }

    public function test_reorder_by_levels(): void
    {
        $this->assertSame(['c', 'd', 'b', 'a'], Bidi::reorderByLevels(['a', 'b', 'c', 'd'], [1, 1, 2, 2]));
    }
}
