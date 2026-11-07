<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Livewire\Component;

/**
 * Product page (roadmap B5 /p/{slug}): a simple version so lists can link here (decided 2026-11-07 in S12) —
 * breadcrumb, photos, brand, name, short description, variants with price and stock. S13 builds the full page
 * (gallery zoom, variant picker, tabs, FAQ, related, add to cart in S19). Only active products are shown.
 */
new class extends Component
{
    public Product $product;

    public function mount(string $slug): void
    {
        $this->product = Product::query()
            ->active()
            ->with(['brand', 'primaryCategory', 'media', 'variants' => fn ($variants) => $variants->where(ProductVariant::COLUMN_IS_ACTIVE, true)])
            ->where(Product::COLUMN_SLUG, $slug)
            ->firstOrFail();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->view()->title($this->product->getSeoTitle() ?? $this->product->getNameFa());
    }
};
?>

@use('App\Modules\Catalog\Enums\StockStatus')

@php
    $category = $product->getPrimaryCategory();
    $trail = $category ? [...$category->ancestors(), $category] : [];
    $images = $product->images($product::IMAGE_GALLERY);
@endphp

<div class="space-y-6">
    <nav aria-label="مسیر صفحه" class="text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700">خانه</a></li>
            @foreach ($trail as $node)
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('category.show', $node) }}" wire:navigate class="hover:text-primary-700">{{ $node->getNameFa() }}</a></li>
            @endforeach
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="line-clamp-1 text-ink-900">{{ $product->getNameFa() }}</li>
        </ol>
    </nav>

    <div class="grid gap-6 md:grid-cols-2 xl:gap-10">
        <div class="space-y-3">
            <div class="aspect-square overflow-hidden rounded-card border border-line-200 bg-white">
                @if ($images !== [])
                    <x-media::picture :media="$images[0]" slot-name="pdp-main" :alt="$product->getNameFa()" :priority="true" class="size-full object-contain" />
                @endif
            </div>
            @if (count($images) > 1)
                <ul class="scrollbar-none flex gap-2 overflow-x-auto">
                    @foreach (array_slice($images, 1) as $image)
                        <li class="size-20 shrink-0 overflow-hidden rounded-md border border-line-200 bg-white">
                            <x-media::picture :media="$image" slot-name="cart-thumb" :alt="$product->getNameFa()" class="size-full object-contain" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="space-y-4">
            @if ($product->getBrand())
                <a href="{{ route('brands.show', $product->getBrand()) }}" wire:navigate class="text-sm text-primary-700 hover:underline">{{ $product->getBrand()->getNameFa() }}</a>
            @endif
            <h1 class="text-xl leading-9 font-bold text-ink-900 xl:text-2xl">{{ $product->getNameFa() }}</h1>
            @if ($product->getNameEn())
                <p dir="ltr" class="text-end text-sm text-ink-500">{{ $product->getNameEn() }}</p>
            @endif
            @if ($product->getShortDescription())
                <p class="leading-8 text-ink-900">{{ $product->getShortDescription() }}</p>
            @endif

            <ul class="divide-y divide-line-200 rounded-card border border-line-200 bg-white">
                @foreach ($product->getVariants() as $variant)
                    <li class="flex items-center justify-between gap-3 p-4">
                        <div class="space-y-1">
                            <p class="font-bold text-ink-900">{{ $variant->getTitleFa() }}</p>
                            <p @class(['text-sm', 'text-success-600' => $variant->getStockStatus() === StockStatus::Available, 'text-ink-500' => $variant->getStockStatus() !== StockStatus::Available])>
                                {{ $variant->getStockStatus()->label() }}
                                @if ($variant->isNearExpiry() && $variant->getExpiryDate())
                                    · زودمصرف (انقضا @jalali($variant->getExpiryDate()))
                                @endif
                            </p>
                        </div>
                        <x-ui.price :price="$variant->currentPrice()" :old="$variant->compareAtPrice()" />
                    </li>
                @endforeach
            </ul>

            @if ($product->getIrcCode())
                <p class="text-sm text-ink-500">کد IRC: <span dir="ltr">{{ $product->getIrcCode() }}</span></p>
            @endif
        </div>
    </div>

    @if ($product->getDescription())
        <section aria-labelledby="product-description" class="rounded-card border border-line-200 bg-white p-4 md:p-6">
            <h2 id="product-description" class="mb-3 text-lg font-bold text-ink-900">معرفی محصول</h2>
            <p class="max-w-3xl leading-8 whitespace-pre-line text-ink-900">{{ $product->getDescription() }}</p>
        </section>
    @endif
</div>
