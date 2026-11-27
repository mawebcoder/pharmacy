<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use Livewire\Component;

/**
 * Category page (roadmap B5, B9.3), also a subcategory's page (owner, 2026-11-15: /categories/{category}/{subcategory},
 * subcategories in their own table): breadcrumb, banner, title, a swipeable row of boxes — «همه» (the category) and
 * its subcategories, the open one marked — and the product list (a category lists its subcategories' products too).
 * Found only while the category, and the subcategory, are active.
 */
new class extends Component
{
    public Category $category;

    public ?Subcategory $subcategory = null;

    public function mount(string $slug, ?string $subcategorySlug = null): void
    {
        $category = Category::query()->with('media')->where(Category::COLUMN_SLUG, $slug)->firstOrFail();
        abort_unless($category->isShownOnStorefront(), 404);
        $this->category = $category;

        if ($subcategorySlug !== null) {
            $subcategory = Subcategory::query()->with('media')
                ->where(Subcategory::COLUMN_CATEGORY_ID, $category->getId())
                ->where(Subcategory::COLUMN_SLUG, $subcategorySlug)
                ->firstOrFail();
            abort_unless($subcategory->isActive(), 404);
            $this->subcategory = $subcategory->setRelation('category', $category);
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $page = $this->subcategory ?? $this->category;

        return $this->view([
            'page' => $page,
            // Only subcategories with at least one product for sale (owner, 2026-11-15).
            'subcategories' => $this->category->subcategories()->active()->withProductsForSale()->with('media')->get(),
        ])->title($page->getSeoTitle() ?? $page->getNameFa());
    }
};
?>

<div class="space-y-6">
    <nav aria-label="مسیر صفحه" class="text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700">خانه</a></li>
            @if ($subcategory)
                <li aria-hidden="true">/</li>
                <li><a href="{{ $category->url() }}" wire:navigate class="hover:text-primary-700">{{ $category->getNameFa() }}</a></li>
            @endif
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-ink-900">{{ $page->getNameFa() }}</li>
        </ol>
    </nav>

    {{-- Banners placed on this category or subcategory (S14); its own banner images are the fallback. --}}
    @php($placedBanners = app(\App\Modules\Content\Queries\PlaceBanners::class)->for($subcategory ? \App\Modules\Content\Enums\BannerPlace::Subcategory : \App\Modules\Content\Enums\BannerPlace::Category, $page->getId()))
    @if ($placedBanners->isNotEmpty())
        <x-content::banners :banners="$placedBanners" :priority="true" />
    @elseif ($page->getBannerDesktop() || $page->getBannerMobile())
        <x-media::art-directed
            :desktop="$page->getBannerDesktop()"
            :mobile="$page->getBannerMobile()"
            desktop-slot="category-banner-desktop"
            mobile-slot="category-banner-mobile"
            :alt="'بنر '.$page->getNameFa()"
            :priority="true"
            class="aspect-4/3 w-full rounded-card object-cover md:aspect-4/1"
        />
    @endif

    <header class="space-y-2">
        <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">{{ $page->getNameFa() }}</h1>
        @if ($page->getDescription())
            <div class="page-content max-w-3xl text-ink-500">{!! \App\Modules\Catalog\Support\DescriptionHtml::toHtml($page->getDescription()) !!}</div>
        @endif
    </header>

    {{-- «همه» and the subcategories as a swipeable row of boxes; the open one is marked (owner, 2026-11-15). --}}
    @if ($subcategories->isNotEmpty())
        @php($boxes = [[$category, true], ...$subcategories->map(fn ($box): array => [$box->setRelation('category', $category), false])->all()])
        <nav aria-label="زیردسته‌ها" class="relative -mx-4 md:mx-0" x-data="slider({ slidesPerView: 'auto', spaceBetween: 8 })">
            <div x-ref="slider" class="swiper px-4! md:px-0!">
                <ul class="swiper-wrapper">
                    @foreach ($boxes as [$box, $isAll])
                        @php($isOpen = $isAll ? $subcategory === null : $box->getId() === $subcategory?->getId())
                        <li wire:key="{{ $isAll ? 'all' : 'subcategory-'.$box->getId() }}" class="swiper-slide w-24! md:w-28!">
                            <a
                                href="{{ $box->url() }}"
                                wire:navigate
                                @if ($isOpen) aria-current="page" @endif
                                @class([
                                    'flex h-full flex-col items-center gap-2 rounded-card border p-2.5 text-center select-none focus-visible:outline-2 focus-visible:outline-primary-700',
                                    'border-primary-700 bg-primary-50 text-primary-700' => $isOpen,
                                    'border-line-200 bg-card text-ink-900 hover:border-primary-300' => ! $isOpen,
                                ])
                            >
                                <span class="grid size-12 place-items-center overflow-hidden rounded-full bg-media p-1.5">
                                    @if ($isAll)
                                        <x-ts-icon icon="squares-2x2" class="size-6 text-primary-700" />
                                    @elseif ($box->getIcon())
                                        <x-media::picture :media="$box->getIcon()" slot-name="category-icon" alt="" class="size-full object-contain" />
                                    @else
                                        <x-ts-icon icon="squares-2x2" class="size-6 text-ink-500" />
                                    @endif
                                </span>
                                <span @class(['line-clamp-2 text-xs leading-5', 'font-bold' => $isOpen])>{{ $isAll ? 'همه' : $box->getNameFa() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-content::slider-arrows previous="زیردسته‌های قبلی" next="زیردسته‌های بعدی" />
        </nav>
    @endif

    @if ($subcategory)
        <livewire:catalog::product-listing :subcategory-id="$subcategory->getId()" :key="'listing-subcategory-'.$subcategory->getId()" />
    @else
        <livewire:catalog::product-listing :category-id="$category->getId()" :key="'listing-category-'.$category->getId()" />
    @endif
</div>
