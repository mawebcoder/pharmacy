<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Pricing\Strategies;

use App\Modules\Shipping\Contracts\ShippingPriceStrategy;
use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Exceptions\ShippingPriceUnavailable;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceRequestAdapter;
use App\Modules\Shipping\Tipax\Adapters\TipaxPriceResponseAdapter;
use App\Modules\Shipping\Tipax\TipaxClient;
use Illuminate\Support\Facades\Cache;

/**
 * Tipax as a shipping price strategy (roadmap S34, Tipax direct; owner 2026-11-18). Tipax prices one service per
 * request, so it first asks which services run between the two cities (kept a day per route), then prices each: our
 * request goes through TipaxPriceRequestAdapter to `POST Pricing`, and each answer comes back through
 * TipaxPriceResponseAdapter as one option. A service Tipax refuses for this order is left out.
 */
final readonly class TipaxShippingPrice implements ShippingPriceStrategy
{
    public function __construct(
        private TipaxClient $client,
        private TipaxPriceRequestAdapter $requestAdapter,
        private TipaxPriceResponseAdapter $responseAdapter,
        private bool $enabled,
        private int $servicesCacheHours,
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
        $options = [];
        $failure = null;

        foreach ($this->services((int) $request->originCityId, $request->destinationCityId) as $service) {
            try {
                $entries = $this->client->price($this->requestAdapter->toBody($request, $service['id']));
            } catch (TipaxRequestFailed $failed) {
                $failure = $failed;

                continue;
            }

            $option = $this->responseAdapter->toOption($entries, count($request->parcels), $service, $request->isEstimate());

            if ($option instanceof ShippingOptionData) {
                $options[] = $option;
            }
        }

        if ($options === [] && $failure instanceof TipaxRequestFailed) {
            throw ShippingPriceUnavailable::from('Tipax', $failure);
        }

        return $options;
    }

    /**
     * @return list<array{id: int, kind: string, days: int|null}>
     *
     * @throws ShippingPriceUnavailable
     */
    private function services(int $originCityId, int $destinationCityId): array
    {
        $key = "shipping:tipax:services:{$originCityId}:{$destinationCityId}";
        $cached = Cache::get($key);

        if (is_array($cached)) {
            /** @var list<array{id: int, kind: string, days: int|null}> $cached */
            return $cached;
        }

        try {
            $services = $this->responseAdapter->toServices($this->client->servicesBetweenCities($originCityId, $destinationCityId));
        } catch (TipaxRequestFailed $failed) {
            throw ShippingPriceUnavailable::from('Tipax', $failed);
        }

        Cache::put($key, $services, now()->addHours($this->servicesCacheHours));

        return $services;
    }
}
