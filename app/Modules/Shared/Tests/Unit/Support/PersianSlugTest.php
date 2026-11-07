<?php

declare(strict_types=1);

use App\Modules\Shared\Support\PersianSlug;

it('makes URL slugs that keep Persian letters', function (string $input, string $expected): void {
    expect(PersianSlug::make($input))->toBe($expected);
})->with([
    'Persian, Latin and digits' => ['ویتامین D3 ۱۰۰۰ واحد', 'ویتامین-d3-1000-واحد'],
    'punctuation and extra spaces' => ['  کرم ضد‌آفتاب (SPF 50)!  ', 'کرم-ضد-آفتاب-spf-50'],
    'Arabic letters normalised' => ['يوروويتال', 'یوروویتال'],
    'Latin only' => ["Nature's Bounty", 'nature-s-bounty'],
]);

it('adds a number when the slug is already taken', function (): void {
    $taken = ['ویتامین-d3', 'ویتامین-d3-2'];

    $slug = PersianSlug::unique('ویتامین D3', fn (string $slug): bool => in_array($slug, $taken, true));

    expect($slug)->toBe('ویتامین-d3-3');
});
