<?php

namespace MostafaMoknaa\ArabicPdf;

use MostafaMoknaa\ArabicPdf\Html\HtmlProcessor;
use MostafaMoknaa\ArabicPdf\Text\Bidi;
use MostafaMoknaa\ArabicPdf\Text\Shaper;

class Arabic
{
    private const ARABIC_PATTERN = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    private const UNSHAPED_LETTER_PATTERN = '/[\x{0621}-\x{064A}\x{0671}-\x{06D3}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u';

    private readonly Shaper $shaper;

    private readonly Bidi $bidi;

    /**
     * @param  array{strip_harakat?: bool, max_chars_per_line?: int|null, rtl_css?: bool, reverse_table_columns?: bool}  $options
     */
    public function __construct(private readonly array $options = [])
    {
        $this->shaper = new Shaper;
        $this->bidi = new Bidi;
    }

    /**
     * Make Arabic text render correctly in dompdf: connect the letters and put the words
     * in visual (right-to-left) order. Lines are separated by "\n".
     *
     * @param  int|null  $maxCharsPerLine  Wrap long paragraphs at this many characters so every line keeps the right order.
     * @param  string|null  $direction  Force the base direction ('rtl' or 'ltr'), detected from the text by default.
     */
    public function fix(string $text, ?int $maxCharsPerLine = null, ?string $direction = null): string
    {
        if (! $this->needsFixing($text)) {
            return $text;
        }

        $maxCharsPerLine ??= $this->options['max_chars_per_line'] ?? null;
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [$text] as $paragraph) {
            $base = $direction ?? Bidi::detectDirection($paragraph) ?? Bidi::RTL;
            $shaped = $this->shape($paragraph);

            foreach ($maxCharsPerLine ? $this->wrap($shaped, $maxCharsPerLine) : [$shaped] as $line) {
                $lines[] = $this->bidi->reorder($line, $base);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Same as fix(), with lines joined by <br> and HTML special characters escaped.
     */
    public function fixForHtml(string $text, ?int $maxCharsPerLine = null, ?string $direction = null): string
    {
        return implode('<br>', array_map(
            static fn (string $line): string => htmlspecialchars($line, ENT_QUOTES, 'UTF-8'),
            explode("\n", $this->fix($text, $maxCharsPerLine, $direction)),
        ));
    }

    /**
     * Fix every Arabic text node of an HTML document or fragment, including RTL tables
     * and inline elements (<b>, <span>, ...) inside right-to-left paragraphs.
     */
    public function html(string $html): string
    {
        return (new HtmlProcessor($this, $this->options))->process($html);
    }

    /**
     * Connect the letters only (logical order is kept).
     */
    public function shape(string $text): string
    {
        if ($this->options['strip_harakat'] ?? false) {
            $text = Shaper::stripHarakat($text);
        }

        return $this->shaper->shape($text);
    }

    /**
     * Convert one line from logical to visual order only (letters are not shaped).
     */
    public function reorder(string $line, string $direction = Bidi::RTL): string
    {
        return $this->bidi->reorder($line, $direction);
    }

    public function containsArabic(string $text): bool
    {
        return preg_match(self::ARABIC_PATTERN, $text) === 1;
    }

    /**
     * Whether the text still has unshaped Arabic letters. Already fixed text is left
     * untouched, which makes fix() safe to call twice.
     */
    public function needsFixing(string $text): bool
    {
        return preg_match(self::UNSHAPED_LETTER_PATTERN, $text) === 1;
    }

    /**
     * @return array<int, string>
     */
    public function wrap(string $text, int $maxChars): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && mb_strlen($candidate, 'UTF-8') > $maxChars) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '' || $lines === []) {
            $lines[] = $current;
        }

        return $lines;
    }
}
