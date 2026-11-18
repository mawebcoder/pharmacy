<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * «بسته‌بندی» under «ارسال» (roadmap S31, owner 2026-11-17/18): the boxes we pack in. How many of each SKU fit a box
 * is set on the SKU form («جا در کارتن‌ها»); the packer uses both to put a paid order into parcels.
 */
final class Packing extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'ارسال';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'بسته‌بندی';

    protected static ?string $clusterBreadcrumb = 'بسته‌بندی';
}
