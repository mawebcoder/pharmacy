<?php

use App\Modules\Catalog\Models\Brand;
use Livewire\Component;

/**
 * Brand page (roadmap B5 /b/{slug}, B9.3): the brand header now; the product list, filters and sort come in S12.
 * Inactive and deleted brands are not found.
 */
new class extends Component
{
    public Brand $brand;

    public function mount(string $slug): void
    {
        $this->brand = Brand::query()->active()->where(Brand::COLUMN_SLUG, $slug)->firstOrFail();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->view()->title($this->brand->getSeoTitle() ?? 'برند '.$this->brand->getNameFa());
    }
};
?>

<div class="space-y-6">
    <nav aria-label="مسیر صفحه" class="text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700">خانه</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('brands.index') }}" wire:navigate class="hover:text-primary-700">برندها</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-ink-900">{{ $brand->getNameFa() }}</li>
        </ol>
    </nav>

    <header class="rounded-card border border-line-200 bg-white p-4 shadow-card md:p-6">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">{{ $brand->getNameFa() }}</h1>
            @if ($brand->getNameEn())
                <span dir="ltr" class="text-ink-500">{{ $brand->getNameEn() }}</span>
            @endif
        </div>
        @if ($brand->getCountryName())
            <p class="mt-2 text-sm text-ink-500">کشور: {{ $brand->getCountryName() }}</p>
        @endif
        @if ($brand->getDescription())
            <p class="mt-4 max-w-3xl leading-8 whitespace-pre-line text-ink-900">{{ $brand->getDescription() }}</p>
        @endif
    </header>

    <section aria-labelledby="brand-products" class="space-y-3">
        <h2 id="brand-products" class="text-lg font-bold text-ink-900">محصولات {{ $brand->getNameFa() }}</h2>
        <div class="rounded-card border border-dashed border-line-200 bg-white px-6 py-12 text-center text-ink-500">
            محصولات این برند به‌زودی اضافه می‌شوند.
        </div>
    </section>
</div>
