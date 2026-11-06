<?php

declare(strict_types=1);

use Illuminate\Support\Number;

it('groups prices in threes with the rial unit', function (int|float|string|null $amount, string $expected): void {
    expect(Number::rial($amount))->toBe($expected);
})->with([
    'below a thousand' => [950, '۹۵۰ ریال'],
    'thousands' => [12_500, '۱۲٬۵۰۰ ریال'],
    'millions' => [4_850_000, '۴٬۸۵۰٬۰۰۰ ریال'],
    'billions' => [1_250_000_000, '۱٬۲۵۰٬۰۰۰٬۰۰۰ ریال'],
    'decimal string from the database' => ['4850000.00', '۴٬۸۵۰٬۰۰۰ ریال'],
    'fractions are rounded' => [999.6, '۱٬۰۰۰ ریال'],
    'null' => [null, '۰ ریال'],
]);

it('can leave the unit out', function (): void {
    expect(Number::rial(4_850_000, withUnit: false))->toBe('۴٬۸۵۰٬۰۰۰');
});
