<?php

declare(strict_types=1);

use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\PackItem;
use App\Modules\Shipping\Enums\ParcelContent;
use App\Modules\Shipping\Packing\BoxPacker;

/*
| Packing by how many of each SKU fit each box (roadmap S31, owner 2026-11-17/18): a box is full when its space or
| its own weight limit (with the box, at most 6 kg) is reached first; fewest boxes, then the smallest box for each.
*/

/**
 * @return list<BoxSpec>
 */
function testBoxes(): array
{
    return [
        new BoxSpec(2, 'بزرگ', 40, 30, 25, 300, 6_000, 7),
        new BoxSpec(1, 'کوچک', 20, 15, 10, 100, 2_000, 2),
    ];
}

/** How many fit each test box (box id => pieces): a small SKU, a medium one, a large one that only the big box takes. */
const SMALL_FIT = [1 => 12, 2 => 60];
const MEDIUM_FIT = [1 => 6, 2 => 24];
const LARGE_FIT = [2 => 10];

/**
 * @param  array<int, int>  $fits
 */
function piece(int $key, int $quantity, array $fits = SMALL_FIT, ?int $weight = 100, bool $alone = false, bool $liquid = false): PackItem
{
    return new PackItem($key, $key, 'کالا '.$key, $quantity, $weight, $fits, 1_000_000, $alone, $liquid);
}

/**
 * @param  list<PackItem>  $items
 * @return list<PackedParcel>
 */
function packed(array $items): array
{
    return app(BoxPacker::class)->pack($items, testBoxes());
}

it('puts a small order in the smallest box that takes it, with the box in the weight', function (): void {
    [$parcel] = packed([piece(1, 3)]);

    expect($parcel->box?->name)->toBe('کوچک')
        ->and($parcel->items)->toBe([['key' => 1, 'variant_id' => 1, 'title' => 'کالا 1', 'quantity' => 3]])
        ->and($parcel->itemsWeightG)->toBe(300)
        ->and($parcel->totalWeightG)->toBe(400)
        ->and($parcel->declaredValue)->toBe(3_000_000)
        ->and($parcel->content)->toBe(ParcelContent::General)
        ->and($parcel->isEstimate)->toBeFalse();
});

it('fills a box by fractions of mixed sizes, and takes a bigger box when they do not fit', function (): void {
    expect(packed([piece(1, 6), piece(2, 3, MEDIUM_FIT)]))->toHaveCount(1)
        ->and(packed([piece(1, 6), piece(2, 3, MEDIUM_FIT)])[0]->box?->name)->toBe('کوچک')
        ->and(packed([piece(1, 7), piece(2, 3, MEDIUM_FIT)])[0]->box?->name)->toBe('بزرگ');
});

it('takes one bigger box rather than two small ones', function (): void {
    $parcels = packed([piece(1, 20)]);

    expect($parcels)->toHaveCount(1)->and($parcels[0]->box?->name)->toBe('بزرگ');
});

it('starts a new box when 6 kg with the box is reached before the space', function (): void {
    $parcels = packed([piece(1, 4, LARGE_FIT, 2_000)]);

    expect($parcels)->toHaveCount(2)
        ->and(array_map(fn (PackedParcel $parcel): int => $parcel->totalWeightG, $parcels))->each->toBeLessThanOrEqual(BoxPacker::MAX_PARCEL_WEIGHT_G)
        ->and(array_sum(array_map(fn (PackedParcel $parcel): int => $parcel->items[0]['quantity'], $parcels)))->toBe(4);
});

it('starts a new box when the space is full before 6 kg', function (): void {
    $parcels = packed([piece(1, 12, LARGE_FIT, 50)]);

    expect($parcels)->toHaveCount(2)
        ->and(array_map(fn (PackedParcel $parcel): ?string => $parcel->box?->name, $parcels))->toBe(['بزرگ', 'بزرگ'])
        ->and(array_map(fn (PackedParcel $parcel): int => $parcel->items[0]['quantity'], $parcels))->toBe([10, 2]);
});

it('takes a bigger box when a small box\'s own weight limit is reached before its space', function (): void {
    // 3 small items of 700 g fit the small box by space (3 of 12) but weigh 2.2 kg with it; it takes 2 kg.
    $parcels = packed([piece(1, 3, SMALL_FIT, 700)]);

    expect($parcels)->toHaveCount(1)
        ->and($parcels[0]->box?->name)->toBe('بزرگ')
        ->and($parcels[0]->totalWeightG)->toBe(2_400);
});

it('uses the fewest boxes, where filling largest first would need one more', function (): void {
    // A box that takes 1 kg: 400 + 400 first, then 300 × 3, then 300 makes 3 boxes; 400 + 300 + 300 twice makes 2.
    $box = new BoxSpec(5, 'کارتن ۱ کیلویی', 30, 20, 20, 1, 1_001, null);
    $items = [piece(1, 2, [5 => 100], 400), piece(2, 4, [5 => 100], 300)];

    $parcels = app(BoxPacker::class)->pack($items, [$box]);

    expect($parcels)->toHaveCount(2)
        ->and(array_map(fn (PackedParcel $parcel): int => $parcel->itemsWeightG, $parcels))->toBe([1_000, 1_000]);
});

it('gives «ارسال جداگانه» items their own box', function (): void {
    $parcels = packed([piece(1, 1), piece(2, 2, alone: true)]);

    expect($parcels)->toHaveCount(3)
        ->and(array_map(fn (PackedParcel $parcel): int => $parcel->items[0]['key'], $parcels))->toBe([1, 2, 2]);
});

it('leaves an item no box takes in a parcel of its own with no box', function (): void {
    $parcels = packed([piece(1, 1), piece(9, 1, LARGE_FIT, 7_000)]);

    expect($parcels)->toHaveCount(2)
        ->and($parcels[1]->box)->toBeNull()
        ->and($parcels[1]->totalWeightG)->toBe(7_000);
});

it('leaves a SKU with no box numbers in a parcel of its own with no box', function (): void {
    $parcels = packed([piece(1, 2), piece(3, 1, [])]);

    expect($parcels)->toHaveCount(2)
        ->and($parcels[0]->box?->name)->toBe('کوچک')
        ->and($parcels[1]->box)->toBeNull()
        ->and($parcels[1]->items[0]['key'])->toBe(3);
});

it('packs a SKU without a weight as 300 g and marks the parcel an estimate', function (): void {
    [$parcel] = packed([piece(1, 1, weight: null)]);

    expect($parcel->itemsWeightG)->toBe(BoxPacker::DEFAULT_WEIGHT_G)
        ->and($parcel->box?->name)->toBe('کوچک')
        ->and($parcel->isEstimate)->toBeTrue();
});

it('marks a parcel liquid-fragile for Tipax when any item in it is', function (): void {
    [$parcel] = packed([piece(1, 1), piece(2, 1, liquid: true)]);

    expect($parcel->content)->toBe(ParcelContent::LiquidFragile)
        ->and($parcel->content->tapinProductType())->toBe(4);
});
