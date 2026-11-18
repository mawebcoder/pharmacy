<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shipping (roadmap D6, D11.3)
|--------------------------------------------------------------------------
|
| Tipax: the OM API (v4; sign-in is only in v3). TIPAX_BASE_URL is the test API (https://omtestapi.tipax.ir) until go-live. Provinces and
| cities come from Tipax (`php artisan tipax:sync-cities`, run on deploy and weekly; decided 2026-11-14 in S20).
| Credentials live only in .env.
|
| Prices (S34, owner 2026-11-18): Tipax direct, priced from our own packing; the codes sent with every package are below. The ids for
| packing, contents and pack type come from Tipax's PackingType endpoints ([CONFIRM] with a test call, roadmap D6 note).
|
*/

use App\Modules\Shipping\Enums\TipaxDistributionType;
use App\Modules\Shipping\Enums\TipaxPaymentType;
use App\Modules\Shipping\Enums\TipaxPickupType;
use App\Modules\Shipping\Enums\TipaxPriceUnit;

return [
    'tipax' => [
        'enabled' => (bool) env('TIPAX_ENABLED', true),
        'base_url' => env('TIPAX_BASE_URL', 'https://omtestapi.tipax.ir'),
        'username' => env('TIPAX_USERNAME'),
        'password' => env('TIPAX_PASSWORD'),
        'api_key' => env('TIPAX_API_KEY'),
        'connect_timeout_seconds' => 5,
        'timeout_seconds' => (int) env('TIPAX_TIMEOUT_SECONDS', 10),
        'retries' => 2,
        'retry_sleep_ms' => 500,
        // Checkout waits on prices, so they get a shorter timeout and no retry; the flat rate stands in on failure.
        'pricing_timeout_seconds' => (int) env('TIPAX_PRICING_TIMEOUT_SECONDS', 5),
        'paths' => [
            'token' => env('TIPAX_PATH_TOKEN', '/api/OM/v3/Account/token'),
            'cities' => env('TIPAX_PATH_CITIES', '/api/OM/v4/Cities/plusstate'),
            'branch_cities' => env('TIPAX_PATH_BRANCH_CITIES', '/api/OM/v4/Cities/BranchCities'),
            'pricing' => env('TIPAX_PATH_PRICING', '/api/OM/v4/Pricing'),
            'services_between_cities' => env('TIPAX_PATH_SERVICES_BETWEEN_CITIES', '/api/OM/v4/Pricing/GetServicesBetweenCities'),
        ],
        'pricing' => [
            // A TipaxPaymentType value: 10 prepaid by the sender (D6.6).
            'payment_type' => (int) env('TIPAX_DEFAULT_PAYMENT_TYPE', TipaxPaymentType::SenderPrepaid->value),
            // A TipaxPickupType value: 20 we drop parcels at a Tipax branch (owner, 2026-11-18); 10 Tipax collects from us.
            'pickup_type' => (int) env('TIPAX_DEFAULT_PICKUP_TYPE', TipaxPickupType::DroppedAtBranch->value),
            // A TipaxDistributionType value: 10 delivered to the receiver's address; 20 collected at a branch.
            'distribution_type' => (int) env('TIPAX_DEFAULT_DISTRIBUTION_TYPE', TipaxDistributionType::ToReceiverAddress->value),
            // «نیاز به بسته‌بندی ندارد»: we pack in our own boxes.
            'packing_id' => (int) env('TIPAX_PACKING_ID', 0),
            'pack_type' => (int) env('TIPAX_PACK_TYPE', 0),
            // «آرایشی، بهداشتی و داروهای مجاز», and «مایعات-شکستنی» for a parcel with any liquid or fragile item.
            'content_general_id' => (int) env('TIPAX_CONTENT_GENERAL_ID', 0),
            'content_liquid_id' => (int) env('TIPAX_CONTENT_LIQUID_ID', 0),
            // A TipaxPriceUnit value: what `finalPrice` is in, 'rial' or 'toman'.
            'price_unit' => env('TIPAX_PRICE_UNIT', TipaxPriceUnit::Rial->value),
            // Sizes sent for a parcel no box takes (staff pack it by hand); its price is marked an estimate.
            'unboxed_size_cm' => 30,
        ],
    ],

    'pricing' => [
        // How long a route and packing keeps its prices (minutes).
        'cache_minutes' => (int) env('TIPAX_QUOTE_TTL_MINUTES', 20),
        // Delivery days per route change rarely (hours).
        'delivery_days_cache_hours' => 24,
        // Shown prices are rounded up to this many rial (1,000 toman).
        'round_up_to_rial' => 10_000,
    ],
];
