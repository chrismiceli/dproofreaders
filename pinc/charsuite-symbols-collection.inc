<?php
include_once($relPath."CharSuites.inc");

use voku\helper\UTF8;

$charsuite = new CharSuite("symbols-collection", _("Symbols collection"));
$charsuite->description = _("Astronomical, zodiac, apothecary and music symbols.");

$charsuite->codepoints = [
    'U+0292',
    'U+2108',
    'U+2114',
    'U+211e',
    'U+2125',
    'U+2609-U+260d',
    'U+263d-U+2646',
    'U+2648>U+fe0e',
    'U+2649>U+fe0e',
    'U+264a>U+fe0e',
    'U+264b>U+fe0e',
    'U+264c>U+fe0e',
    'U+264d>U+fe0e',
    'U+264e>U+fe0e',
    'U+264f>U+fe0e',
    'U+2650>U+fe0e',
    'U+2651>U+fe0e',
    'U+2652>U+fe0e',
    'U+2653>U+fe0e',
    'U+2669-U+266a',
    'U+266d-U+266f',
];

$charsuite->reference_urls = [
    "https://www.pgdp.net/wiki/Symbols_collection",
];

$pickerset = new PickerSet();

$pickerset->add_subset(UTF8::hex_to_chr("U+263d"), [
    [
        'U+2609', 'U+263d', 'U+263f', 'U+2641', 'U+2643', 'U+2645', 'U+260a',
        'U+260c', 'U+266e', 'U+266d', 'U+266f',
    ],
    [
        null, 'U+263e', 'U+2640', 'U+2642', 'U+2644', 'U+2646', 'U+260b',
        'U+260d', null, 'U+2669-U+266a',
    ],
], _("Astronomical and music symbols"));

$pickerset->add_subset(utf8_combined_chr("U+2648>U+fe0e"), [
    [
        'U+2648>U+fe0e', 'U+2649>U+fe0e', 'U+264a>U+fe0e', 'U+264b>U+fe0e',
        'U+264c>U+fe0e', 'U+264d>U+fe0e', 'U+211e', 'U+0292', 'U+2108',
    ],
    [
        'U+264e>U+fe0e', 'U+264f>U+fe0e', 'U+2650>U+fe0e', 'U+2651>U+fe0e',
        'U+2652>U+fe0e', 'U+2653>U+fe0e', null, 'U+2125', 'U+2114',
    ],
], _("Zodiac and apothecary symbols"));

$charsuite->pickerset = $pickerset;

CharSuites::add($charsuite);
