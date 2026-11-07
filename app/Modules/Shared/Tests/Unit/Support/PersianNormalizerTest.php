<?php

declare(strict_types=1);

use App\Modules\Shared\Support\PersianNormalizer;

it('makes Persian text comparable', function (string $input, string $expected): void {
    expect(PersianNormalizer::normalize($input))->toBe($expected);
})->with([
    'Arabic ye and kaf, spaces and Latin case' => ['  ويتامين  D3 كودك ', 'ویتامین d3 کودک'],
    'teh marbuta and hamza forms' => ['قهوة أصل إسپری', 'قهوه اصل اسپری'],
    'alef with madda is kept' => ['آب', 'آب'],
    'half-space becomes a space' => ["ضد\u{200C}آفتاب", 'ضد آفتاب'],
    'diacritics removed' => ['بِسْمِ', 'بسم'],
    'tatweel removed' => ['ســلام', 'سلام'],
    'Persian and Arabic digits' => ['ویتامین ۱۰۰۰ واحد ٥', 'ویتامین 1000 واحد 5'],
    'line breaks and tabs' => ["قرص\n\tجوشان", 'قرص جوشان'],
]);

it('converts only digits when asked', function (): void {
    expect(PersianNormalizer::digits('۰۹۱۲-١٢٣'))->toBe('0912-123');
});

it('indexes text with and without the spaces between its parts', function (string $input, string $expected): void {
    expect(PersianNormalizer::forIndex($input))->toBe($expected);
})->with([
    'two parts' => ['ضد آفتاب', 'ضد آفتاب ضدآفتاب'],
    'half-space' => ["ضد\u{200C}آفتاب", 'ضد آفتاب ضدآفتاب'],
    'one word' => ['ویتامین', 'ویتامین'],
]);

it('treats names as equal regardless of spacing and letter forms', function (string $first, string $second, bool $equal): void {
    expect(PersianNormalizer::equals($first, $second))->toBe($equal);
})->with([
    'space vs none' => ['بندر عباس', 'بندرعباس', true],
    'Arabic letters' => ['كرمانشاه', 'کرمانشاه', true],
    'half-space' => ["بندر\u{200C}عباس", 'بندر عباس', true],
    'different cities' => ['تهران', 'تبریز', false],
]);
