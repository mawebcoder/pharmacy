<?php

declare(strict_types=1);

use App\Modules\Shared\Support\SearchText;

it('prepares text for search the same way for products and queries', function (string $input, string $expected): void {
    expect(SearchText::normalize($input))->toBe($expected);
})->with([
    'Arabic letters, Persian digits and units' => ['ويتامين D3 ۱۰۰۰ واحد', 'ویتامین d 3 1000 iu'],
    'units stuck to the number' => ['1000IU', '1000 iu'],
    'milligrams in Persian' => ['زینک ۵۰ میلی‌گرم', 'زینک 50 mg'],
    'alef with madda as alef' => ['ضدآفتاب', 'ضدافتاب'],
    'half-space and punctuation' => ["کرم ضد\u{200C}آفتاب (SPF50+)", 'کرم ضد افتاب spf 50'],
    'stopwords removed' => ['شامپو برای مو چرب', 'شامپو مو چرب'],
]);

it('indexes joined forms of neighbouring words', function (): void {
    expect(SearchText::forIndex('کرم ضد آفتاب'))->toBe('کرم ضد افتاب کرمضد ضدافتاب');
});

it('lists the words of a query once each', function (): void {
    expect(SearchText::words('ویتامین D ویتامین'))->toBe(['ویتامین', 'd']);
});

/*
| The owner's Persian normalization guide (2026-11-28), its test table as a regression suite: add a row for every bug.
*/
it('normalises the guide\'s cases the same way for documents and queries', function (string $input, string $expected): void {
    expect(SearchText::normalize($input))->toBe($expected);
})->with([
    'Arabic kaf, half-space, plural ending as a stop word' => ['كتاب‌ها', 'کتاب'],
    'detached plural ending' => ['کتاب ها', 'کتاب'],
    'Arabic yeh and the detached prefix' => ['مي روم', 'می روم'],
    'repeated half-space' => ["می\u{200C}\u{200C}روم", 'می روم'],
    'hamza forms and the variant map' => ['هیئت مدیره', 'هیات مدیره'],
    'hamza on alef and the variant map' => ['هیأت مدیره', 'هیات مدیره'],
    'variant map' => ['مسئله', 'مساله'],
    'madda and Arabic letters' => ['آمريكا', 'امریکا'],
    'Persian digits' => ['۱۴۰۳ سال', '1403 سال'],
    'tatweel' => ['سـلام', 'سلام'],
    'heh with hamza, one character' => ['خانۀ من', 'خانه من'],
    'heh with hamza, two characters' => ['خانهٔ من', 'خانه من'],
    'spelling variant' => ['اطاق خواب', 'اتاق خواب'],
    'pharmacy spelling variant' => ['پوماد سوختگی', 'پماد سوختگی'],
    'standalone hamza' => ['اجزاء', 'اجزا'],
    'zero-width space does not split a word' => ["کر\u{200B}م", 'کرم'],
    'direction marks and byte-order mark' => ["\u{FEFF}\u{200F}کرم\u{200E} D3", 'کرم d 3'],
    'Arabic presentation forms (NFKC)' => ['ﻛﺘﺎﺏ', 'کتاب'],
    'swash kaf' => ['ڪرم', 'کرم'],
    'never «بدون»' => ['بدون شکر', 'بدون شکر'],
]);

it('gives letters that sound the same one sound key', function (): void {
    expect(SearchText::phonetic('ضابطه'))->toBe('زابته')
        ->and(SearchText::phonetic('زابطه'))->toBe('زابته')
        ->and(SearchText::phonetic('صابون'))->toBe(SearchText::phonetic('سابون'))
        ->and(SearchText::phoneticForIndex('ضد آفتاب'))->toBe('زد افتاب زدافتاب');
});

it('converts text typed on the other keyboard layout, and only text of one layout', function (string $typed, ?string $meant): void {
    expect(SearchText::otherLayout($typed))->toBe($meant);
})->with([
    'English layout on, Persian meant' => ['sghl', 'سلام'],
    'Persian layout on, English meant' => ['لخخلمث', 'google'],
    'mixed scripts' => ['کرم d3', null],
]);

it('turns Persian typed in Latin letters into a sound skeleton', function (string $typed, ?string $skeleton): void {
    expect(SearchText::finglish($typed))->toBe($skeleton);
})->with([
    ['kerem', 'کرم'],
    ['ghors', 'قرس'],
    ['shampoo', 'شامپو'],
    ['vitamin', 'ویتامین'],
    ['cinere', 'سینره'],
    ['کرم', null],
]);

it('normalises English the same way as Persian', function (string $input, string $expected): void {
    expect(SearchText::normalize($input))->toBe($expected);
})->with([
    'accents' => ['Avène Crème', 'avene creme'],
    'apostrophe inside a word' => ["L'Oréal Johnson’s", 'loreal johnson'],
    'plural' => ['Moisturizing Creams', 'moisturizing cream'],
    'plural in -ies' => ['Berries', 'berry'],
    'endings that are not plurals' => ['glass plus iris gas', 'glass plus iris gas'],
    'stopwords, keeping vitamin A and E' => ['The Cream for Kids with Vitamin A and E', 'cream kid vitamin a e'],
    'British spelling' => ['Moisturising Moisturiser Colours', 'moisturizing moisturizer color'],
    'full-width letters and invisible characters' => ["ＣＩＮＥＲＥ\u{200B} cream", 'cinere cream'],
]);

it('gives English letters that sound the same one sound key', function (): void {
    expect(SearchText::phonetic('cyneere'))->toBe(SearchText::phonetic('Cinere'))->toBe('sinere')
        ->and(SearchText::phonetic('Phenol Zinc Quick'))->toBe('fenol sink kuik');
});
