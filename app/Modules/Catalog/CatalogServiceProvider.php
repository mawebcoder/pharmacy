<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Listeners\SavePendingWishlistProduct;
use App\Modules\Identity\Events\CustomerLoggedIn;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

final class CatalogServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // A guest's heart click is saved once they sign in (roadmap S18).
        Event::listen(CustomerLoggedIn::class, SavePendingWishlistProduct::class);
    }
}
