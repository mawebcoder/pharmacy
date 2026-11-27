<?php

use App\Modules\Catalog\Models\Brand;
use Livewire\Component;

/**
 * Brand page (roadmap B5 /brands/{slug}, B9.3): the brand header and its product list.
 * Inactive and deleted brands are not found.
 */
new class extends Component
{
    public Brand $brand;

    public function mount(string $slug): void
    {
        $this->brand = Brand::query()->active()->with('media')->where(Brand::COLUMN_SLUG, $slug)->firstOrFail();
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

    <x-content::banners place="brand" :place-id="$brand->getId()" :priority="true" />

    <header class="flex flex-col gap-4 rounded-card border border-line-200 bg-card p-4 shadow-card md:flex-row md:items-start md:p-6">
        @if ($brand->getLogo())
            <x-media::picture :media="$brand->getLogo()" slot-name="brand-logo" :alt="'لوگوی '.$brand->getNameFa()" :priority="true" class="size-16 shrink-0 rounded-full bg-white object-contain p-2 md:size-20 md:p-3 xl:size-24" />
        @endif
        <div class="min-w-0 flex-1">
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
                <div class="page-content mt-4 max-w-3xl">{!! \App\Modules\Catalog\Support\DescriptionHtml::toHtml($brand->getDescription()) !!}</div>
            @endif
        </div>
    </header>

    <livewire:catalog::product-listing :brand-id="$brand->getId()" />
</div>
