# Changelog

All notable changes to this package are documented in this file.

## v1.0.0 - 2026-09-28

- Initial release.
- Arabic and Persian letter shaping, including lam-alef ligatures.
- Right-to-left reordering based on the Unicode Bidirectional Algorithm (numbers and Latin text stay left to right).
- Inline elements and RTL tables are reordered in HTML documents.
- Optional line wrapping for long paragraphs (`data-arabic-wrap`, `max_chars_per_line`).
- `ArabicPdf` facade (`loadView`, `loadHTML`, `loadFile`, `stream`, `download`, `save`, `output`).
- `Arabic` facade and `@arabic` Blade directive for use with other PDF libraries.
