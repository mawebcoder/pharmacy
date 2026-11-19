<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Pricing;

use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Contracts\ShippingPriceStrategy;
use App\Modules\Shipping\Data\ShippingOptionData;
use App\Modules\Shipping\Data\ShippingPriceRequest;
use App\Modules\Shipping\Exceptions\ShippingPriceUnavailable;
use App\Modules\Shipping\Pricing\Strategies\FlatRateShippingPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The context of the shipping price strategies (roadmap S34, strategy pattern; owner 2026-11-18): asks every strategy
 * that supports the request, keeps each one's answer for a while per route and packing, adds the store's markup to
 * the services' prices and rounds them up, and offers all options cheapest first. When no strategy answers, the
 * fallbacks are tried in order (the rate table «نرخ ارسال جایگزین», then the flat rate; S31, owner 2026-11-19).
 */
final readonly class ShippingPriceCalculator
{
    /**
     * @param  iterable<ShippingPriceStrategy>  $strategies
     * @param  list<ShippingPriceStrategy>  $fallbacks  tried in order; the last always answers
     */
    public function __construct(
        private iterable $strategies,
        private array $fallbacks,
        private StoreSettings $settings,
        private int $cacheMinutes,
        private int $roundUpToRial,
    ) {}

    /**
     * @return list<ShippingOptionData> never empty: the flat rate when nothing else answers
     */
    public function options(ShippingPriceRequest $request): array
    {
        $options = [];

        foreach ($this->strategies as $strategy) {
            if (! $strategy->supports($request)) {
                continue;
            }

            try {
                $options = [...$options, ...$this->cached($strategy, $request)];
            } catch (ShippingPriceUnavailable $unavailable) {
                Log::warning('Shipping price unavailable.', ['strategy' => $strategy->key(), 'reason' => $unavailable->getMessage()]);
            }
        }

        if ($options === []) {
            foreach ($this->fallbacks as $fallback) {
                if ($fallback->supports($request) && ($fallbackOptions = $fallback->options($request)) !== []) {
                    return $fallbackOptions;
                }
            }

            return [FlatRateShippingPrice::option($this->settings->shippingFlatRate())];
        }

        $markup = $this->settings->shippingMarkupPercent();
        $options = array_map(fn (ShippingOptionData $option): ShippingOptionData => $option->withPrice($this->rounded($option->price * (100 + $markup) / 100)), $options);
        usort($options, fn (ShippingOptionData $a, ShippingOptionData $b): int => $a->price <=> $b->price);

        return $options;
    }

    /**
     * A strategy's options, from the cache when the same route and packing was priced lately. Failures are not kept.
     *
     * @return list<ShippingOptionData>
     *
     * @throws ShippingPriceUnavailable
     */
    private function cached(ShippingPriceStrategy $strategy, ShippingPriceRequest $request): array
    {
        $key = "shipping:price:{$strategy->key()}:{$request->cacheKey()}";
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return array_values(array_map(fn (array $option): ShippingOptionData => ShippingOptionData::fromArray($option), $cached));
        }

        $options = $strategy->options($request);
        Cache::put($key, array_map(fn (ShippingOptionData $option): array => $option->toArray(), $options), now()->addMinutes($this->cacheMinutes));

        return $options;
    }

    private function rounded(float $price): int
    {
        return $this->roundUpToRial > 0 ? (int) (ceil($price / $this->roundUpToRial) * $this->roundUpToRial) : (int) ceil($price);
    }
}
