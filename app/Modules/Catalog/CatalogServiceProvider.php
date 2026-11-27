<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Events\AttributeValueSearchFieldsChanged;
use App\Modules\Catalog\Events\BrandSearchFieldsChanged;
use App\Modules\Catalog\Events\CategorySearchFieldsChanged;
use App\Modules\Catalog\Events\ProductsSearchFieldsChanged;
use App\Modules\Catalog\Events\SubcategorySearchFieldsChanged;
use App\Modules\Catalog\Events\TagSearchFieldsChanged;
use App\Modules\Catalog\Jobs\SyncFlashDeals;
use App\Modules\Catalog\Listeners\NotifyAdminOfImportChunk;
use App\Modules\Catalog\Listeners\QueueProductReindex;
use App\Modules\Catalog\Listeners\SavePendingWishlistProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Events\CustomerLoggedIn;
use App\Modules\Shared\ModuleServiceProvider;
use Filament\Actions\Imports\Events\ImportChunkProcessed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

final class CatalogServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // A guest's heart click is saved once they sign in (roadmap S20).
        Event::listen(CustomerLoggedIn::class, SavePendingWishlistProduct::class);
        // Catalog import (owner, 2026-11-27): the admin hears about failed rows after each chunk.
        Event::listen(ImportChunkProcessed::class, NotifyAdminOfImportChunk::class);

        // Product search documents are rebuilt from these events only, in bulk (owner, 2026-11-27): a product's
        // document also copies its SKUs, links, brand and categories, so Scout's save-time indexing would be both
        // early (before the links are saved) and incomplete. Brands and categories keep Scout's own syncing.
        Product::disableSearchSyncing();
        Event::listen([
            BrandSearchFieldsChanged::class,
            CategorySearchFieldsChanged::class,
            SubcategorySearchFieldsChanged::class,
            TagSearchFieldsChanged::class,
            AttributeValueSearchFieldsChanged::class,
            ProductsSearchFieldsChanged::class,
        ], QueueProductReindex::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Flash deals that started or ended put or take their price on the SKU (roadmap S28).
            $schedule->job(new SyncFlashDeals)->everyMinute()->onOneServer();
            // The search indexes are checked against the database every hour and every product re-pushed, so a lost
            // update cannot stay lost (owner, 2026-11-27).
            $schedule->command('search:reconcile')->hourly()->onOneServer()->withoutOverlapping();
        });
    }
}
