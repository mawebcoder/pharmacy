<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Actions\ImportProductImages;
use App\Modules\Catalog\Filament\Imports\ProductImporter;
use App\Modules\Catalog\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * After a catalog import row is saved (owner, 2026-11-27): downloads the product's image URLs into its gallery. URLs
 * that could not be imported are sent to the admin who imported, under the panel's bell (notify_admin).
 */
final class DownloadImportedProductImages implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  list<string>  $imageUrls
     */
    public function __construct(
        public readonly int $productId,
        public readonly array $imageUrls,
        public readonly ?int $adminId,
    ) {
        $this->onQueue(ProductImporter::QUEUE);
    }

    public function handle(ImportProductImages $importImages): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product instanceof Product) {
            return;
        }

        $problems = $importImages($product, $this->imageUrls);

        if ($problems !== [] && $this->adminId !== null) {
            notify_admin(
                $this->adminId,
                'ورود کالا: عکس‌های «'.$product->getNameFa().'» کامل وارد نشد',
                implode("\n", $problems),
                'warning',
                ProductResource::getUrl('edit', ['record' => $product->getId()], panel: 'admin'),
            );
        }
    }
}
