<?php

declare(strict_types=1);

use App\Modules\Shared\Support\Money;

it('keeps amounts in whole rial and shows them in toman', function (): void {
    $total = Money::rial(48_500_000)->multiply(2)->add(Money::toman(25_000));

    expect($total->getRial())->toBe(97_250_000)
        ->and($total->getToman())->toBe(9_725_000)
        ->and($total->formatToman())->toBe('۹٬۷۲۵٬۰۰۰ تومان')
        ->and($total->formatToman(withUnit: false))->toBe('۹٬۷۲۵٬۰۰۰');
});

it('rounds a percentage to whole rial', function (int $rial, float $percent, int $expected): void {
    expect(Money::rial($rial)->percent($percent)->getRial())->toBe($expected);
})->with([
    'exact' => [97_250_000, 17, 16_532_500],
    'half rounds up' => [15, 10, 2],
    'fraction of a percent' => [1_000_000, 2.5, 25_000],
]);

it('allows negative amounts such as margins', function (): void {
    $margin = Money::rial(100)->subtract(Money::rial(250));

    expect($margin->isNegative())->toBeTrue()
        ->and($margin->getRial())->toBe(-150);
});

it('compares amounts', function (): void {
    $small = Money::toman(1_000);
    $large = Money::rial(20_000);

    expect($small->lessThan($large))->toBeTrue()
        ->and($large->greaterThan($small))->toBeTrue()
        ->and($small->equals(Money::rial(10_000)))->toBeTrue()
        ->and($large->min($small))->toBe($small)
        ->and(Money::zero()->isZero())->toBeTrue();
});

it('never changes an existing amount', function (): void {
    $price = Money::rial(1_000);

    $price->add(Money::rial(500));

    expect($price->getRial())->toBe(1_000);
});

it('serialises to its rial value', function (): void {
    expect(json_encode(['total' => Money::rial(12_500_000)]))->toBe('{"total":12500000}');
});
