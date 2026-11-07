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
