<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Database\Seeders;

use App\Modules\Shipping\Models\ShippingBox;
use Illuminate\Database\Seeder;

/**
 * Tipax's standard cartons 1–11 as our starting boxes (roadmap S31, owner 2026-11-17; sizes from Tapin's guide, pack
 * types «کارتن سایز ۱–۱۱»). Their own weights are estimated from the cardboard area plus filler, and small thin
 * cartons take less than 6 kg, all for the admin to correct. Running it again adds only cartons that are missing and
 * keeps the admin's edits.
 *
 *   php artisan db:seed --class="App\Modules\Shipping\Database\Seeders\TipaxCartonsSeeder"
 */
final class TipaxCartonsSeeder extends Seeder
{
    /**
     * Tipax carton number => [length, width, height] in cm.
     */
    public const array CARTONS = [
        1 => [15, 10, 10], 2 => [20, 15, 10], 3 => [20, 20, 15], 4 => [30, 20, 20], 5 => [35, 25, 20], 6 => [45, 25, 20],
        7 => [40, 30, 25], 8 => [45, 40, 30], 9 => [55, 45, 35], 10 => [35, 25, 10], 11 => [45, 35, 10],
    ];

    /**
     * The most a parcel in a carton may weigh, box included (g): thin small cartons less, the rest Tapin's 6 kg.
     */
    private const array MAX_WEIGHTS = [1 => 2000, 2 => 3000, 3 => 4000, 10 => 3000];

    public function run(): void
    {
        foreach (self::CARTONS as $size => [$length, $width, $height]) {
            if (ShippingBox::query()->where(ShippingBox::COLUMN_TIPAX_SIZE, $size)->exists()) {
                continue;
            }

            // About 0.045 g of three-ply cardboard per cm² of surface, plus filler; rounded to 10 g.
            $area = 2 * ($length * $width + $length * $height + $width * $height);

            (new ShippingBox)
                ->setName('کارتن سایز '.$size)
                ->setTipaxSize($size)
                ->setLengthCm($length)
                ->setWidthCm($width)
                ->setHeightCm($height)
                ->setEmptyWeightG((int) (round(($area * 0.045 + ($area > 5000 ? 30 : 20)) / 10) * 10))
                ->setMaxWeightG(self::MAX_WEIGHTS[$size] ?? 6000)
                ->setSort($length * $width * $height)
                ->setIsActive(true)
                ->save();
        }
    }
}
