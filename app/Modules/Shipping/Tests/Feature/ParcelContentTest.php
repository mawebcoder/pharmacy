<?php

declare(strict_types=1);

use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackItem;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Packing\BoxPacker;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceRequestAdapter;

/*
| The Tipax content of a parcel from its products (owner, 2026-11-19): liquid or fragile wins, else the content its
| products share, else the store's general content.
*/

function contentOf(PackItem ...$items): int
{
    $box = new BoxSpec(1, 'کارتن', 30, 20, 20, 150, 6000, 4);
    $parcels = (new BoxPacker)->pack($items, [$box]);

    return app(TipaxPriceRequestAdapter::class)->toBody(new ShippingPriceRequest(1199, 1262, $parcels), 1)['packageInputs'][0]['packageContentId'];
}

function contentItem(int $key, ?int $contentId, bool $liquid = false): PackItem
{
    return new PackItem($key, $key, 'کالا '.$key, 1, 200, [1 => 10], 1_000_000, liquidOrFragile: $liquid, tipaxContentId: $contentId);
}

it('sends the content the parcel\'s products share', function (): void {
    expect(contentOf(contentItem(1, 37), contentItem(2, 37)))->toBe(37);
});

it('sends the store\'s general content when the products differ or name none', function (): void {
    expect(contentOf(contentItem(1, 37), contentItem(2, 16)))->toBe(9)
        ->and(contentOf(contentItem(1, 37), contentItem(2, null)))->toBe(9)
        ->and(contentOf(contentItem(1, null)))->toBe(9);
});

it('sends liquid-fragile when any product is liquid or fragile, whatever the others say', function (): void {
    expect(contentOf(contentItem(1, 37), contentItem(2, 37, liquid: true)))->toBe(1);
});
