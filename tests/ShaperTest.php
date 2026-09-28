<?php

namespace MostafaMoknaa\ArabicPdf\Tests;

use MostafaMoknaa\ArabicPdf\Text\Shaper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShaperTest extends TestCase
{
    /**
     * @return array<string, array{string, array<int, int>}>
     */
    public static function words(): array
    {
        return [
            'initial, medial, final' => ['محمد', [0xFEE3, 0xFEA4, 0xFEE4, 0xFEAA]],
            'lam alef ligature' => ['سلام', [0xFEB3, 0xFEFC, 0xFEE1]],
            'isolated lam alef' => ['لا', [0xFEFB]],
            'non-joining letters break the word' => ['دار', [0xFEA9, 0xFE8D, 0xFEAD]],
            'single letter is isolated' => ['ب', [0xFE8F]],
            'teh marbuta final' => ['مدرسة', [0xFEE3, 0xFEAA, 0xFEAD, 0xFEB3, 0xFE94]],
            'persian letters' => ['پیک', [0xFB58, 0xFBFF, 0xFB8F]],
        ];
    }

    /**
     * @param  array<int, int>  $expected
     */
    #[DataProvider('words')]
    public function test_it_shapes_letters_by_position(string $word, array $expected): void
    {
        $this->assertSame($expected, $this->codes((new Shaper)->shape($word)));
    }

    public function test_harakat_do_not_break_joining(): void
    {
        $this->assertSame([0xFEE3, 0x064F, 0xFEA4, 0xFEE4, 0x064E, 0xFEAA], $this->codes((new Shaper)->shape('مُحمَد')));
    }

    public function test_non_arabic_text_is_untouched(): void
    {
        $this->assertSame('Hello 123 !', (new Shaper)->shape('Hello 123 !'));
    }

    public function test_it_strips_harakat(): void
    {
        $this->assertSame('محمد', Shaper::stripHarakat('مُحَمَّد'));
    }

    /**
     * @return array<int, int>
     */
    private function codes(string $text): array
    {
        return array_map(static fn (string $char): int => mb_ord($char, 'UTF-8'), mb_str_split($text));
    }
}
