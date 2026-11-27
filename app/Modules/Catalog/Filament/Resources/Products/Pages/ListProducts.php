<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\Pages;

use App\Modules\Catalog\Filament\Imports\ProductImporter;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

final class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Catalog import from CSV (owner, 2026-11-27): products, SKUs, brands and categories, 100 rows per job.
            ImportAction::make()
                ->label('ورود کالا از فایل')
                ->modalHeading('ورود کالا از فایل CSV')
                ->importer(ProductImporter::class)
                ->chunkSize(ProductImporter::CHUNK_SIZE)
                ->visible(fn (): bool => auth('admin')->user()?->can('create', Product::class) ?? false),
            CreateAction::make(),
        ];
    }
}
