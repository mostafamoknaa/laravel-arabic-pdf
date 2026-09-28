<?php

namespace MostafaMoknaa\ArabicPdf\Text;

/**
 * Converts Arabic (and Persian) letters to their contextual presentation forms
 * (isolated / final / initial / medial) so they render connected in PDF engines
 * that don't implement OpenType shaping, such as dompdf.
 */
final class Shaper
{
    /**
     * [isolated, final, initial, medial]. A null final form means the letter never joins
     * the previous letter; a null initial form means it never joins the next one.
     *
     * @var array<int, array{0: int, 1: ?int, 2: ?int, 3: ?int}>
     */
    private const FORMS = [
        0x0621 => [0xFE80, null, null, null],     // ء
        0x0622 => [0xFE81, 0xFE82, null, null],   // آ
        0x0623 => [0xFE83, 0xFE84, null, null],   // أ
        0x0624 => [0xFE85, 0xFE86, null, null],   // ؤ
        0x0625 => [0xFE87, 0xFE88, null, null],   // إ
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], // ئ
        0x0627 => [0xFE8D, 0xFE8E, null, null],   // ا
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92], // ب
        0x0629 => [0xFE93, 0xFE94, null, null],   // ة
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98], // ت
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C], // ث
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], // ج
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4], // ح
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8], // خ
        0x062F => [0xFEA9, 0xFEAA, null, null],   // د
        0x0630 => [0xFEAB, 0xFEAC, null, null],   // ذ
        0x0631 => [0xFEAD, 0xFEAE, null, null],   // ر
        0x0632 => [0xFEAF, 0xFEB0, null, null],   // ز
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], // س
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8], // ش
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC], // ص
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], // ض
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], // ط
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8], // ظ
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], // ع
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0], // غ
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640], // ـ tatweel
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4], // ف
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], // ق
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC], // ك
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0], // ل
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], // م
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8], // ن
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC], // ه
        0x0648 => [0xFEED, 0xFEEE, null, null],   // و
        0x0649 => [0xFEEF, 0xFEF0, 0xFBE8, 0xFBE9], // ى
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4], // ي
        0x0671 => [0xFB50, 0xFB51, null, null],   // ٱ
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], // پ
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D], // چ
        0x0698 => [0xFB8A, 0xFB8B, null, null],   // ژ
        0x06A4 => [0xFB6A, 0xFB6B, 0xFB6C, 0xFB6D], // ڤ
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], // ک
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95], // گ
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF], // ی
    ];

    /**
     * Lam + alef ligatures: alef code point => [isolated, final].
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6], // لآ
        0x0623 => [0xFEF7, 0xFEF8], // لأ
        0x0625 => [0xFEF9, 0xFEFA], // لإ
        0x0627 => [0xFEFB, 0xFEFC], // لا
    ];

    private const LAM = 0x0644;

    public function shape(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $chars = mb_str_split($text, 1, 'UTF-8');
        $codes = array_map(static fn (string $char): int => mb_ord($char, 'UTF-8'), $chars);
        $count = count($codes);
        $output = '';

        for ($i = 0; $i < $count; $i++) {
            $code = $codes[$i];

            if (! isset(self::FORMS[$code])) {
                $output .= $chars[$i];

                continue;
            }

            $prev = $this->neighbour($codes, $i, -1);
            $next = $this->neighbour($codes, $i, 1);
            $joinsPrev = $prev !== null && $this->joinsForward($codes[$prev]) && self::FORMS[$code][1] !== null;

            if ($code === self::LAM && $next !== null && isset(self::LAM_ALEF[$codes[$next]])) {
                $output .= mb_chr(self::LAM_ALEF[$codes[$next]][$joinsPrev ? 1 : 0], 'UTF-8');

                for ($k = $i + 1; $k < $next; $k++) {
                    $output .= $chars[$k];
                }

                $i = $next;

                continue;
            }

            $joinsNext = $next !== null && $this->joinsForward($code) && self::FORMS[$codes[$next]][1] !== null;

            $form = match (true) {
                $joinsPrev && $joinsNext => self::FORMS[$code][3],
                $joinsPrev => self::FORMS[$code][1],
                $joinsNext => self::FORMS[$code][2],
                default => self::FORMS[$code][0],
            };

            $output .= mb_chr($form, 'UTF-8');
        }

        return $output;
    }

    public static function isTransparent(int $code): bool
    {
        return ($code >= 0x0610 && $code <= 0x061A)
            || ($code >= 0x064B && $code <= 0x065F)
            || $code === 0x0670
            || ($code >= 0x06D6 && $code <= 0x06DC)
            || ($code >= 0x06DF && $code <= 0x06E4)
            || $code === 0x06E7
            || $code === 0x06E8
            || ($code >= 0x06EA && $code <= 0x06ED);
    }

    public static function stripHarakat(string $text): string
    {
        return preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E4}\x{06E7}\x{06E8}\x{06EA}-\x{06ED}]/u', '', $text) ?? $text;
    }

    private function joinsForward(int $code): bool
    {
        return isset(self::FORMS[$code]) && self::FORMS[$code][2] !== null;
    }

    /**
     * Index of the closest letter in the given direction, skipping harakat.
     * Returns null when that neighbour is missing or is not a joinable letter.
     *
     * @param  array<int, int>  $codes
     */
    private function neighbour(array $codes, int $index, int $step): ?int
    {
        for ($i = $index + $step; isset($codes[$i]); $i += $step) {
            if (self::isTransparent($codes[$i])) {
                continue;
            }

            return isset(self::FORMS[$codes[$i]]) ? $i : null;
        }

        return null;
    }
}
