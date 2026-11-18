<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Pricing\Strategies;

use App\Modules\Shipping\Contracts\ShippingPriceStrategy;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Exceptions\ShippingPriceUnavailable;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceRequestAdapter;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceResponseAdapter;
use App\Modules\Shipping\Tipax\TipaxClient;
use Illuminate\Support\Facades\Cache;

/**
 * Tipax as a shipping price strategy (roadmap S34, Tipax direct; owner 2026-11-18): our request goes through
 * TipaxPriceRequestAdapter to `POST Pricing`, and the answer comes back through TipaxPriceResponseAdapter as one
 * option per service family on the route. Delivery days per route are asked once a day; without them the options
 * still stand.
 */
final readonly class TipaxShippingPrice implements ShippingPriceStrategy
{
    public function __construct(
        private TipaxClient $client,
        private TipaxPriceRequestAdapter $requestAdapter,
        private TipaxPriceResponseAdapter $responseAdapter,
        private bool $enabled,
        private int $deliveryDaysCacheHours,
    ) {}

    public function key(): string
    {
        return 'tipax';
    }

    public function supports(ShippingPriceRequest $request): bool
    {
        return $this->enabled && $this->client->isConfigured() && $request->originCityId !== null && $request->parcels !== [];
    }

    public function options(ShippingPriceRequest $request): array
    {
        try {
            $entries = $this->client->price($this->requestAdapter->toBody($request));
        } catch (TipaxRequestFailed $failed) {
            throw ShippingPriceUnavailable::from('Tipax', $failed);
        }

        return $this->responseAdapter->toOptions(
            $entries,
            count($request->parcels),
            $this->deliveryDays((int) $request->originCityId, $request->destinationCityId),
            $request->isEstimate(),
        );
    }

    /**
     * @return array<int, int> service id => delivery days; empty when Tipax does not say
     */
    private function deliveryDays(int $originCityId, int $destinationCityId): array
    {
        $key = "shipping:tipax:delivery_days:{$originCityId}:{$destinationCityId}";
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $services = $this->client->servicesBetweenCities($originCityId, $destinationCityId);
        } catch (TipaxRequestFailed) {
            return [];
        }

        $days = [];

        foreach ($services as $service) {
            if (is_numeric($service['serviceId'] ?? null) && is_numeric($service['deliveryDay'] ?? null)) {
                $days[(int) $service['serviceId']] = (int) $service['deliveryDay'];
            }
        }

        Cache::put($key, $days, now()->addHours($this->deliveryDaysCacheHours));

        return $days;
    }
}
