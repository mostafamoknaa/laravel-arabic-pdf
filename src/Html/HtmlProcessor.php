<?php

namespace MostafaMoknaa\ArabicPdf\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use MostafaMoknaa\ArabicPdf\Arabic;
use MostafaMoknaa\ArabicPdf\Text\Bidi;

class HtmlProcessor
{
    private const SKIP_TAGS = ['script', 'style', 'head', 'title', 'textarea', 'svg', 'math', 'template'];

    /**
     * Only these elements (and text) flow inline and get reordered inside RTL paragraphs;
     * any other element, including <br>, is treated as a boundary.
     */
    private const INLINE_TAGS = [
        'a', 'abbr', 'b', 'bdi', 'big', 'cite', 'code', 'del', 'dfn', 'em', 'font', 'i', 'img', 'ins', 'kbd',
        'label', 'mark', 'q', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'time', 'tt', 'u', 'var',
    ];

    private const RTL_CSS = '[dir="rtl"] { text-align: right; } [dir="ltr"] { text-align: left; }';

    private const ENTITY_MAP = [0x80, 0x10FFFF, 0, 0x1FFFFF];

    private const FRAGMENT_ID = '__arabic_pdf_fragment__';

    /**
     * @param  array{max_chars_per_line?: int|null, rtl_css?: bool, reverse_table_columns?: bool}  $options
     */
    public function __construct(private readonly Arabic $arabic, private readonly array $options = []) {}

    public function process(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $isDocument = preg_match('/<html[\s>]|<body[\s>]|<!doctype/i', $html) === 1;
        $encoded = mb_encode_numericentity($html, self::ENTITY_MAP, 'UTF-8');

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        if ($isDocument) {
            $dom->loadHTML($encoded, LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        } else {
            $dom->loadHTML(
                '<html><body><div id="'.self::FRAGMENT_ID.'">'.$encoded.'</div></body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $isDocument ? $dom->documentElement : $dom->getElementById(self::FRAGMENT_ID);

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $this->walk($root, null, null, false, $this->options['max_chars_per_line'] ?? null);

        if ($isDocument) {
            if ($this->options['rtl_css'] ?? true) {
                $this->injectCss($dom);
            }

            $output = $dom->saveHTML();
        } else {
            $output = ($this->options['rtl_css'] ?? true) ? '<style>'.self::RTL_CSS.'</style>' : '';

            foreach ($root->childNodes as $child) {
                $output .= $dom->saveHTML($child);
            }
        }

        return mb_decode_numericentity((string) $output, self::ENTITY_MAP, 'UTF-8');
    }

    private function walk(DOMElement $element, ?string $inheritedDir, ?string $parentBase, bool $preformatted, ?int $wrap): void
    {
        $tag = strtolower($element->nodeName);

        if (in_array($tag, self::SKIP_TAGS, true) || $element->hasAttribute('data-arabic-skip')) {
            return;
        }

        $ownDir = $this->dirAttribute($element);
        $explicitDir = $ownDir ?? $inheritedDir;
        $preformatted = $preformatted || $tag === 'pre';

        if ($element->hasAttribute('data-arabic-wrap')) {
            $wrap = (int) $element->getAttribute('data-arabic-wrap') ?: null;
        }

        $base = $ownDir
            ?? (in_array($tag, self::INLINE_TAGS, true) ? $parentBase : null)
            ?? $explicitDir
            ?? Bidi::detectDirection($element->textContent)
            ?? Bidi::LTR;

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMText) {
                $this->fixTextNode($child, $base, $preformatted, $wrap);
            } elseif ($child instanceof DOMElement) {
                $this->walk($child, $explicitDir, $base, $preformatted, $wrap);
            }
        }

        if ($tag === 'tr' && $explicitDir === Bidi::RTL) {
            if ($this->options['reverse_table_columns'] ?? true) {
                $this->reverseChildren($element);
            }
        } elseif ($base === Bidi::RTL) {
            $this->reorderInlineChildren($element);
        }
    }

    private function fixTextNode(DOMText $node, string $base, bool $preformatted, ?int $wrap): void
    {
        $text = $node->nodeValue ?? '';

        if (! $this->arabic->needsFixing($text)) {
            return;
        }

        if (! $preformatted) {
            $text = preg_replace('/[ \t\r\n\f]+/', ' ', $text) ?? $text;
        }

        $fixed = $this->arabic->fix($text, $wrap, $base);

        if ($preformatted || ! str_contains($fixed, "\n")) {
            $node->nodeValue = $fixed;

            return;
        }

        $document = $node->ownerDocument;
        $parent = $node->parentNode;

        foreach (explode("\n", $fixed) as $index => $line) {
            if ($index > 0) {
                $parent->insertBefore($document->createElement('br'), $node);
            }

            $parent->insertBefore($document->createTextNode($line), $node);
        }

        $parent->removeChild($node);
    }

    /**
     * Inside a right-to-left paragraph, sibling inline nodes must be displayed from right
     * to left too. Runs of left-to-right siblings (e.g. English words) keep their order.
     */
    private function reorderInlineChildren(DOMElement $element): void
    {
        $segment = [];

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement && ! in_array(strtolower($child->nodeName), self::INLINE_TAGS, true)) {
                $this->reorderSegment($element, $segment);
                $segment = [];

                continue;
            }

            $segment[] = $child;
        }

        $this->reorderSegment($element, $segment);
    }

    /**
     * @param  array<int, DOMNode>  $nodes
     */
    private function reorderSegment(DOMElement $parent, array $nodes): void
    {
        if (count($nodes) < 2) {
            return;
        }

        $types = [];
        $lastStrong = 'R';

        foreach ($nodes as $node) {
            $text = $node->textContent;
            $type = match (true) {
                $this->arabic->containsArabic($text) => 'R',
                preg_match('/\p{L}/u', $text) === 1 => 'L',
                preg_match('/\p{N}/u', $text) === 1 => $lastStrong,
                default => 'N',
            };

            if ($type !== 'N') {
                $lastStrong = $type;
            }

            $types[] = $type;
        }

        $levels = [];

        foreach ($types as $i => $type) {
            if ($type !== 'N') {
                $levels[] = $type === 'L' ? 2 : 1;

                continue;
            }

            $before = $this->nearestStrong($types, $i, -1);
            $after = $this->nearestStrong($types, $i, 1);
            $levels[] = $before === 'L' && $after === 'L' ? 2 : 1;
        }

        $ordered = Bidi::reorderByLevels($nodes, $levels);

        if ($ordered === $nodes) {
            return;
        }

        $reference = end($nodes)->nextSibling;

        foreach ($nodes as $node) {
            $parent->removeChild($node);
        }

        foreach ($ordered as $node) {
            $parent->insertBefore($node, $reference);
        }
    }

    /**
     * @param  array<int, string>  $types
     */
    private function nearestStrong(array $types, int $index, int $step): ?string
    {
        for ($i = $index + $step; isset($types[$i]); $i += $step) {
            if ($types[$i] !== 'N') {
                return $types[$i];
            }
        }

        return null;
    }

    private function reverseChildren(DOMElement $element): void
    {
        foreach (array_reverse(iterator_to_array($element->childNodes)) as $child) {
            $element->appendChild($child);
        }
    }

    private function dirAttribute(DOMElement $element): ?string
    {
        $dir = strtolower(trim($element->getAttribute('dir')));

        return in_array($dir, [Bidi::RTL, Bidi::LTR], true) ? $dir : null;
    }

    private function injectCss(DOMDocument $dom): void
    {
        $html = $dom->documentElement;

        if (! $html instanceof DOMElement) {
            return;
        }

        $head = $dom->getElementsByTagName('head')->item(0);

        if (! $head instanceof DOMElement) {
            $head = $dom->createElement('head');
            $html->insertBefore($head, $html->firstChild);
        }

        $style = $dom->createElement('style', self::RTL_CSS);
        $head->insertBefore($style, $head->firstChild);
    }
}
