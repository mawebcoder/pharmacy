<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Data\ProviderData;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Jobs\RecalculateVariantPrices;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Queries\SupplierPrices;

/**
 * Creates or updates a supplier. Switching a supplier on or off re-prices every SKU it has a price for, in the
 * background (its prices are ignored while it is off).
 */
final readonly class SaveProvider
{
    public function __construct(private SupplierPrices $supplierPrices) {}

    #[\NoDiscard]
    public function __invoke(ProviderData $data, ?Provider $provider = null, ?int $adminId = null): Provider
    {
        $provider ??= new Provider;
        $activeChanged = $provider->exists && $provider->isActive() !== $data->isActive;

        $provider->setName($data->name)
            ->setContactName($data->contactName)
            ->setPhone($data->phone)
            ->setAddress($data->address)
            ->setCityId($data->cityId)
            ->setPaymentTerms($data->paymentTerms)
            ->setIsActive($data->isActive)
            ->setNotes($data->notes)
            ->save();

        if ($activeChanged) {
            RecalculateVariantPrices::dispatch($this->supplierPrices->variantIdsOfProvider($provider->getId()), PriceChangeReason::ProviderPriceChanged, $adminId)->afterCommit();
        }

        return $provider;
    }
}
