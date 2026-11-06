<?php

declare(strict_types=1);

use App\Modules\Shared\Support\Toman;

it('formats rial as toman grouped in threes with Persian digits', function (int|float|string|null $rial, string $expected): void {
    expect(Toman::format($rial))->toBe($expected);
})->with([
    'below a thousand toman' => [9_500, '۹۵۰ تومان'],
    'thousands' => [125_000, '۱۲٬۵۰۰ تومان'],
    'roadmap example' => [12_500_000, '۱٬۲۵۰٬۰۰۰ تومان'],
    'billions of toman' => [12_500_000_000, '۱٬۲۵۰٬۰۰۰٬۰۰۰ تومان'],
    'decimal string from the database' => ['48500000.00', '۴٬۸۵۰٬۰۰۰ تومان'],
    'half a toman rounds up' => [12_345, '۱٬۲۳۵ تومان'],
    'zero' => [0, '۰ تومان'],
    'null' => [null, '۰ تومان'],
]);

it('leaves the unit out when asked', function (): void {
    expect(Toman::format(12_500_000, withUnit: false))->toBe('۱٬۲۵۰٬۰۰۰');
});

it('converts toman typed by a person into rial', function (int|string|null $typed, ?int $rial): void {
    expect(Toman::rialFromInput($typed))->toBe($rial);
})->with([
    'plain number' => ['485000', 4_850_000],
    'Latin thousands separators' => ['485,000', 4_850_000],
    'Persian digits and separators' => ['۴۸۵٬۰۰۰', 4_850_000],
    'integer' => [1_250_000, 12_500_000],
    'empty input' => ['', null],
    'null' => [null, null],
]);

it('calculates the discount percent between an old and a new price', function (int $old, int $new, int $percent): void {
    expect(Toman::discountPercent($old, $new))->toBe($percent);
})->with([
    'rounded to a whole percent' => [15_000_000, 12_500_000, 17],
    'no discount when the price is unchanged' => [15_000_000, 15_000_000, 0],
    'no discount when the new price is higher' => [15_000_000, 16_000_000, 0],
    'no discount without an old price' => [0, 12_500_000, 0],
]);
