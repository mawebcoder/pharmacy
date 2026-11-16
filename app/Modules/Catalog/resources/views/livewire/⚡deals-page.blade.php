<?php

use App\Modules\Catalog\Livewire\ProductListing;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Queries\FlashDealProducts;
use App\Modules\Catalog\Queries\NearExpiryProducts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * «تخفیف‌ها» (/deals, roadmap B5, S33): the live flash deals with one countdown to the last deal's end, the discount
 * tiers as chips that open the discounted products (/products?off=N), and the «زودمصرف» row (the product list has no
 * near-expiry filter; owner, 2026-11-17). When the countdown reaches zero the page refreshes.
 */
new #[Title('تخفیف‌ها')] class extends Component
{
    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function flashDeals(): Collection
    {
        return app(FlashDealProducts::class)->live();
    }

    #[Computed]
    public function endsAt(): ?Carbon
    {
        return app(FlashDealProducts::class)->countdownEnd();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function nearExpiry(): Collection
    {
        return app(NearExpiryProducts::class)->row();
    }

    /**
     * @return list<int>
     */
    public function discountSteps(): array
    {
        return ProductListing::DISCOUNT_STEPS;
    }
};
?>

@use('App\Modules\Shared\Support\PersianDigits')

<div class="space-y-8">
    <h1 class="text-2xl font-bold text-ink-900">تخفیف‌ها</h1>

    <section aria-labelledby="flash-deals" class="space-y-3">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <h2 id="flash-deals" class="flex items-center gap-1.5 text-lg font-bold text-ink-900 xl:text-xl">
                <x-ts-icon icon="bolt" class="size-6 text-accent-600" />
                پیشنهاد لحظه‌ای
            </h2>
            <a href="{{ route('products.index', ['deal' => 1]) }}" wire:navigate class="ms-auto order-last flex min-h-11 items-center gap-1 text-sm font-bold text-primary-700 hover:underline">
                مشاهده همه با فیلتر
                <x-ts-icon icon="chevron-left" class="size-4" />
            </a>
            @if ($this->endsAt)
                <p class="inline-flex items-center gap-1.5 rounded-full bg-accent-500/10 px-3 text-sm leading-8 text-accent-600" x-data="countdown(@js($this->endsAt->toIso8601String()), () => $wire.$refresh())">
                    <x-ts-icon icon="clock" class="size-4" />
                    <span class="sr-only">زمان باقی‌مانده:</span>
                    <span class="font-bold tabular-nums" dir="ltr" x-text="remaining"></span>
                </p>
            @endif
        </div>
        @if ($this->flashDeals->isEmpty())
            <p class="rounded-card border border-line-200 bg-card px-4 py-6 text-center text-sm text-ink-500">الان پیشنهاد لحظه‌ای فعالی نیست؛ به‌زودی سر بزنید.</p>
        @else
            <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-5">
                @foreach ($this->flashDeals as $product)
                    <li wire:key="deal-{{ $product->getId() }}"><x-catalog::product-card :product="$product" /></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="discount-tiers" class="space-y-3">
        <h2 id="discount-tiers" class="text-lg font-bold text-ink-900 xl:text-xl">بر اساس میزان تخفیف</h2>
        <ul class="flex flex-wrap gap-2">
            @foreach ($this->discountSteps() as $step)
                <li>
                    <a href="{{ route('products.index', ['off' => $step]) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-full border border-accent-500 px-4 text-sm font-bold text-accent-600 hover:bg-accent-500/10 focus-visible:outline-2 focus-visible:outline-accent-600">
                        {{ PersianDigits::toPersian($step) }}٪ تخفیف و بیشتر
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($this->nearExpiry->isNotEmpty())
        <section aria-labelledby="near-expiry" class="space-y-3">
            <h2 id="near-expiry" class="text-lg font-bold text-ink-900 xl:text-xl">زودمصرف</h2>
            <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-6">
                @foreach ($this->nearExpiry as $product)
                    <li wire:key="near-expiry-{{ $product->getId() }}"><x-catalog::product-card :product="$product" /></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
