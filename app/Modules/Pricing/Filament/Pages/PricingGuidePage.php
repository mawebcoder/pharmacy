<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Pages;

use App\Modules\Catalog\Filament\Pages\VariantsWithoutSuppliers;
use App\Modules\Pricing\Filament\Resources\PriceImports\PriceImportResource;
use App\Modules\Pricing\Filament\Resources\Providers\ProviderResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * «راهنمای قیمت‌گذاری» (owner, 2026-11-15): one page that teaches how suppliers, commission and the pricing engine
 * work (roadmap D3.2), with definitions, worked examples, where each thing is set and common questions. Open to
 * every admin: it shows no costs or data.
 */
final class PricingGuidePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'قیمت‌گذاری';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'راهنمای قیمت‌گذاری';

    protected static ?string $title = 'راهنمای قیمت‌گذاری';

    protected static ?string $slug = 'pricing-guide';

    protected string $view = 'pricing::filament.pricing-guide';

    public static function canAccess(): bool
    {
        return auth('admin')->check();
    }

    /**
     * Links to the places the guide talks about, for the admin's own permissions.
     *
     * @return array<string, string|null>
     */
    protected function getViewData(): array
    {
        $can = fn (string $permission): bool => auth('admin')->user()?->can($permission) ?? false;

        return [
            'providersUrl' => $can('ViewAny:Provider') ? ProviderResource::getUrl() : null,
            'importsUrl' => $can('ViewAny:PriceImport') ? PriceImportResource::getUrl() : null,
            'withoutSuppliersUrl' => $can('ViewAny:Provider') ? VariantsWithoutSuppliers::getUrl() : null,
        ];
    }
}
