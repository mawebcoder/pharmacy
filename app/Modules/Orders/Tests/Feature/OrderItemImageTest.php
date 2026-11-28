<?php

declare(strict_types=1);

use App\Modules\Orders\Models\OrderItem;

it('offers the AVIF copy of a product photo snapshot, and nothing for any other address', function (?string $snapshot, ?string $avif): void {
    expect((new OrderItem)->setImageUrl($snapshot)->getImageAvifUrl())->toBe($avif);
})->with([
    'product photo' => ['https://hutana.ir/storage/media/a/b/x-product-320-webp.webp', 'https://hutana.ir/storage/media/a/b/x-product-320-avif.avif'],
    'another preset' => ['https://hutana.ir/storage/media/a/b/x-brand_logo-320-webp.webp', null],
    'no photo' => [null, null],
]);
