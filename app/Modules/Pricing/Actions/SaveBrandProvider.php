<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Data\BrandProviderData;
use App\Modules\Pricing\Models\BrandProvider;

/**
 * Sets a supplier's commission for a brand (roadmap D3.2, S15b, level 4). The brand's SKUs that buy from this
 * supplier are re-priced in the background.
 */
final class SaveBrandProvider
{
    #[\NoDiscard]
    public function __invoke(BrandProviderData $data, ?BrandProvider $row = null, ?int $adminId = null): BrandProvider
    {
        $row ??= new BrandProvider;
        $row->setBrandId($data->brandId)
            ->setProviderId($data->providerId)
            ->setCommissionPercent($data->commissionPercent)
            ->save();

        DeleteBrandProvider::reprice($row, $adminId);

        return $row;
    }
}
