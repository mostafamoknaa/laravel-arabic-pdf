<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paper
    |--------------------------------------------------------------------------
    |
    | Default paper size (a4, letter, legal, ...) and orientation (portrait or
    | landscape). Both can be changed per document with ->setPaper().
    |
    */

    'paper' => 'a4',

    'orientation' => 'portrait',

    /*
    |--------------------------------------------------------------------------
    | Default font
    |--------------------------------------------------------------------------
    |
    | The font must contain the Arabic presentation forms. "DejaVu Sans" ships
    | with dompdf and works out of the box. Custom fonts (Cairo, Amiri, ...)
    | can be loaded with @font-face in your view, see the README.
    |
    */

    'default_font' => 'DejaVu Sans',

    /*
    |--------------------------------------------------------------------------
    | Arabic text
    |--------------------------------------------------------------------------
    |
    | strip_harakat:          Remove diacritics (tashkeel) before rendering.
    | max_chars_per_line:     Wrap long Arabic paragraphs after this many
    |                         characters so every line keeps the right word
    |                         order. Null disables it; it can also be set per
    |                         element with the data-arabic-wrap="60" attribute.
    | rtl_css:                Right-align dir="rtl" elements (left-align dir="ltr").
    | reverse_table_columns:  Show table columns right-to-left inside dir="rtl".
    |
    */

    'strip_harakat' => false,

    'max_chars_per_line' => null,

    'rtl_css' => true,

    'reverse_table_columns' => true,

    /*
    |--------------------------------------------------------------------------
    | Dompdf options
    |--------------------------------------------------------------------------
    |
    | Any option accepted by Dompdf\Options. See
    | https://github.com/dompdf/dompdf/blob/master/src/Options.php
    |
    */

    'dompdf' => [
        'fontDir' => storage_path('fonts'),
        'fontCache' => storage_path('fonts'),
        'tempDir' => sys_get_temp_dir(),
        'chroot' => realpath(base_path()),
        'isRemoteEnabled' => false,
        'isPhpEnabled' => false,
        'isHtml5ParserEnabled' => true,
        'dpi' => 96,
    ],

];
