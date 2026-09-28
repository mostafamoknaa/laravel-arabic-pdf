<?php

namespace MostafaMoknaa\ArabicPdf\Text;

/**
 * A compact implementation of the Unicode Bidirectional Algorithm (UAX #9) for a single
 * line without explicit embeddings. It converts logical order to visual order, so text
 * can be drawn left-to-right by engines that have no bidi support.
 */
final class Bidi
{
    public const LTR = 'ltr';

    public const RTL = 'rtl';

    private const MIRRORS = [
        '(' => ')', ')' => '(',
        '[' => ']', ']' => '[',
        '{' => '}', '}' => '{',
        '<' => '>', '>' => '<',
        '«' => '»', '»' => '«',
        '‹' => '›', '›' => '‹',
    ];

    /**
     * Direction of the first strong character, or null when the text has none.
     */
    public static function detectDirection(string $text): ?string
    {
        if (preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]|\p{L}/u', $text, $match) !== 1) {
            return null;
        }

        return self::classify(mb_ord($match[0], 'UTF-8')) === 'R' ? self::RTL : self::LTR;
    }

    public function reorder(string $line, string $base = self::RTL): string
    {
        if ($line === '') {
            return $line;
        }

        $clusters = preg_split('/(\P{M}\p{M}*)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($clusters === false) {
            return $line;
        }

        $types = array_map(static fn (string $cluster): string => self::classify(mb_ord($cluster, 'UTF-8')), $clusters);
        $levels = $this->resolveLevels($types, $base);

        foreach ($clusters as $i => $cluster) {
            if ($levels[$i] % 2 === 1) {
                $first = mb_substr($cluster, 0, 1, 'UTF-8');

                if (isset(self::MIRRORS[$first])) {
                    $clusters[$i] = self::MIRRORS[$first].mb_substr($cluster, 1, null, 'UTF-8');
                }
            }
        }

        return implode('', self::reorderByLevels($clusters, $levels));
    }

    /**
     * Rule L2: from the highest level down to the lowest odd level, reverse every
     * contiguous run at that level or higher.
     *
     * @template T
     *
     * @param  array<int, T>  $items
     * @param  array<int, int>  $levels
     * @return array<int, T>
     */
    public static function reorderByLevels(array $items, array $levels): array
    {
        $items = array_values($items);
        $levels = array_values($levels);

        if ($items === []) {
            return $items;
        }

        $max = max($levels);
        $odd = array_filter($levels, static fn (int $level): bool => $level % 2 === 1);
        $lowestOdd = $odd === [] ? $max + 1 : min($odd);
        $count = count($items);

        for ($level = $max; $level >= $lowestOdd; $level--) {
            for ($i = 0; $i < $count; $i++) {
                if ($levels[$i] < $level) {
                    continue;
                }

                $end = $i;

                while ($end + 1 < $count && $levels[$end + 1] >= $level) {
                    $end++;
                }

                $length = $end - $i + 1;
                array_splice($items, $i, $length, array_reverse(array_slice($items, $i, $length)));
                array_splice($levels, $i, $length, array_reverse(array_slice($levels, $i, $length)));
                $i = $end;
            }
        }

        return $items;
    }

    /**
     * Bidi class of a code point: R, L, EN (European number), AN (Arabic number),
     * ES, ET, CS (number separators / terminators), WS or ON (other neutral).
     */
    public static function classify(int $code): string
    {
        return match (true) {
            $code >= 0x30 && $code <= 0x39, $code >= 0x06F0 && $code <= 0x06F9 => 'EN',
            $code >= 0x0660 && $code <= 0x0669, $code === 0x066B, $code === 0x066C => 'AN',
            $code === 0x2B, $code === 0x2D => 'ES',
            in_array($code, [0x23, 0x24, 0x25, 0xA2, 0xA3, 0xA5, 0xB0, 0x066A, 0x2030, 0x20AC], true) => 'ET',
            in_array($code, [0x2C, 0x2E, 0x2F, 0x3A, 0xA0, 0x060C], true) => 'CS',
            in_array($code, [0x09, 0x0A, 0x0B, 0x0C, 0x0D, 0x20, 0x2028, 0x2029], true) => 'WS',
            ($code >= 0x0590 && $code <= 0x08FF) || ($code >= 0xFB1D && $code <= 0xFDFF) || ($code >= 0xFE70 && $code <= 0xFEFF) => 'R',
            preg_match('/\p{L}|\p{N}/u', mb_chr($code, 'UTF-8')) === 1 => 'L',
            default => 'ON',
        };
    }

    /**
     * @param  array<int, string>  $types
     * @return array<int, int>
     */
    private function resolveLevels(array $types, string $base): array
    {
        $count = count($types);
        $baseType = $base === self::RTL ? 'R' : 'L';
        $isNumber = static fn (string $type): bool => $type === 'EN' || $type === 'AN';

        // W4: a single separator between two numbers becomes part of the number.
        for ($i = 1; $i < $count - 1; $i++) {
            if (($types[$i] === 'ES' || $types[$i] === 'CS') && $isNumber($types[$i - 1]) && $types[$i + 1] === $types[$i - 1]) {
                $types[$i] = $types[$i - 1];
            }
        }

        // W5: terminators (%, $, ...) adjacent to numbers become numbers.
        for ($i = 0; $i < $count; $i++) {
            if ($types[$i] !== 'ET') {
                continue;
            }

            $end = $i;

            while ($end + 1 < $count && $types[$end + 1] === 'ET') {
                $end++;
            }

            $before = $i > 0 ? $types[$i - 1] : null;
            $after = $end + 1 < $count ? $types[$end + 1] : null;

            if ($before === 'EN' || $after === 'EN') {
                for ($k = $i; $k <= $end; $k++) {
                    $types[$k] = 'EN';
                }
            }

            $i = $end;
        }

        // W6 + W7: leftover separators are neutral; numbers following L text behave as L.
        $lastStrong = $baseType;

        for ($i = 0; $i < $count; $i++) {
            if (in_array($types[$i], ['ES', 'ET', 'CS'], true)) {
                $types[$i] = 'ON';
            } elseif ($types[$i] === 'L' || $types[$i] === 'R') {
                $lastStrong = $types[$i];
            } elseif ($types[$i] === 'EN' && $lastStrong === 'L') {
                $types[$i] = 'L';
            }
        }

        // N1 + N2: neutrals take the direction of the surrounding text, or the base direction.
        $strongOf = static fn (string $type): ?string => match ($type) {
            'L' => 'L',
            'R', 'EN', 'AN' => 'R',
            default => null,
        };

        for ($i = 0; $i < $count; $i++) {
            if ($strongOf($types[$i]) !== null) {
                continue;
            }

            $end = $i;

            while ($end + 1 < $count && $strongOf($types[$end + 1]) === null) {
                $end++;
            }

            $before = $i > 0 ? $strongOf($types[$i - 1]) : $baseType;
            $after = $end + 1 < $count ? $strongOf($types[$end + 1]) : $baseType;
            $resolved = $before === $after ? $before : $baseType;

            for ($k = $i; $k <= $end; $k++) {
                $types[$k] = $resolved;
            }

            $i = $end;
        }

        // I1 + I2: implicit levels.
        $baseLevel = $base === self::RTL ? 1 : 0;
        $levels = [];

        foreach ($types as $type) {
            $levels[] = match (true) {
                $baseLevel === 0 && $type === 'R' => 1,
                $baseLevel === 0 && $type !== 'L' => 2,
                $baseLevel === 1 && $type !== 'R' => 2,
                default => $baseLevel,
            };
        }

        return $levels;
    }
}
