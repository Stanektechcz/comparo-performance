<?php

use App\Domain\Shared\Text\TextFold;
use App\Domain\Shared\Text\TitleSimilarity;

it('folds case and strips combining accents without trimming', function (string $input, string $folded) {
    expect(TextFold::fold($input))->toBe($folded);
})->with([
    'accents' => ['Créatine Monohydraté', 'creatine monohydrate'],
    'precomposed vs combining' => ["e\u{0301}clair", 'eclair'],
    'dotted capital I' => ['İstanbul', 'istanbul'],
    'no decomposition for Æ Ø ß' => ['ÆØß', 'æøß'],
    'greek final sigma' => ['ΣΊΣΥΦΟΣ', 'σισυφος'],
    'keeps surrounding whitespace' => ["  Whey\u{00A0}\t", "  whey\u{00A0}\t"],
    'empty' => ['', ''],
]);

it('reduces text to lowercase ASCII words separated by single spaces', function (string $input, string $canonical) {
    expect(TextFold::canonical($input))->toBe($canonical);
})->with([
    'punctuation' => ['WHEY ISOLATE 90 - Vanilla 900g | IRONFORGE', 'whey isolate 90 vanilla 900g ironforge'],
    'non-breaking space and tab' => ["Whey\u{00A0}Isolate\t90\n", 'whey isolate 90'],
    'emoji' => ['Whey 💪 Protein 🥛', 'whey protein'],
    'fullwidth letters are not ASCII' => ['Ｗｈｅｙ', ''],
    'whitespace only' => ["   \t ", ''],
    'sharp s splits the word' => ['straße', 'stra e'],
]);

it('keeps tokens longer than two characters in order with duplicates', function () {
    expect(TextFold::tokens('B-12 / D3+K2 whey whey (90 caps)'))->toBe(['whey', 'whey', 'caps'])
        ->and(TextFold::tokens(''))->toBe([]);
});

it('scrubs invalid UTF-8 instead of failing', function () {
    expect(TextFold::fold("Whey\xC3\x28"))->toBe('whey?(')
        ->and(TextFold::canonical("\xFF\xFEWhey"))->toBe('whey')
        ->and(TitleSimilarity::score("\xFF", "\xFF"))->toBe(0.0);
});

it('combines Jaccard and trigram Dice, rounded to two decimals', function () {
    expect(TitleSimilarity::score('whey whey whey', 'whey'))->toBe(0.79)
        ->and(TitleSimilarity::score('a b c d e f', 'a b c d e f'))->toBe(0.5)
        ->and(TitleSimilarity::score('abc', 'abc'))->toBe(1.0)
        ->and(TitleSimilarity::score('ab', 'ab'))->toBe(0.0)
        ->and(TitleSimilarity::jaccard('123 456 7890', '123 456'))->toBe(2 / 3);
});

it('does not reproduce the prototype constructor-token quirk (deviation #4)', function () {
    expect(TitleSimilarity::jaccard('constructor whey', 'isolate whey'))->toBe(1 / 3);
});
