<?php

declare(strict_types=1);

use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Checkout\CheckoutServiceProvider;
use App\Modules\Content\ContentServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Locations\LocationsServiceProvider;
use App\Modules\Media\MediaServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Pricing\PricingServiceProvider;
use App\Modules\Reporting\ReportingServiceProvider;
use App\Modules\Search\SearchServiceProvider;
use App\Modules\Seo\SeoServiceProvider;
use App\Modules\Settings\SettingsServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Modules\Shipping\ShippingServiceProvider;
use App\Modules\System\SystemServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    CartServiceProvider::class,
    CatalogServiceProvider::class,
    CheckoutServiceProvider::class,
    ContentServiceProvider::class,
    IdentityServiceProvider::class,
    LocationsServiceProvider::class,
    MediaServiceProvider::class,
    NotificationsServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
    PricingServiceProvider::class,
    ReportingServiceProvider::class,
    SearchServiceProvider::class,
    SeoServiceProvider::class,
    SettingsServiceProvider::class,
    SharedServiceProvider::class,
    ShippingServiceProvider::class,
    SystemServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
