<?php

use App\Modules\Catalog\Models\Category;
use Livewire\Component;

/**
 * Category page (roadmap B5 /c/{slug}, B9.3): breadcrumb, banner, title, subcategory chips and the product list
 * (products of this category and the ones below it). A category is found only when it and every category above it are active.
 */
new class extends Component
{
    public Category $category;

    public function mount(string $slug): void
    {
        $category = Category::query()->with('media')->where(Category::COLUMN_SLUG, $slug)->firstOrFail();

        abort_unless($category->isShownOnStorefront(), 404);

        $this->category = $category;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->view([
            'ancestors' => $this->category->ancestors(),
            'subcategories' => $this->category->children()->active()->with('media')->get(),
        ])->title($this->category->getSeoTitle() ?? $this->category->getNameFa());
    }
};
?>

<div class="space-y-6">
    <nav aria-label="مسیر صفحه" class="text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700">خانه</a></li>
            @foreach ($ancestors as $ancestor)
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('category.show', $ancestor) }}" wire:navigate class="hover:text-primary-700">{{ $ancestor->getNameFa() }}</a></li>
            @endforeach
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-ink-900">{{ $category->getNameFa() }}</li>
        </ol>
    </nav>

    @if ($category->getBannerDesktop() || $category->getBannerMobile())
        <x-media::art-directed
            :desktop="$category->getBannerDesktop()"
            :mobile="$category->getBannerMobile()"
            desktop-slot="category-banner-desktop"
            mobile-slot="category-banner-mobile"
            :alt="'بنر '.$category->getNameFa()"
            :priority="true"
            class="aspect-4/3 w-full rounded-card object-cover md:aspect-4/1"
        />
    @endif

    <header class="space-y-2">
        <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">{{ $category->getNameFa() }}</h1>
        @if ($category->getDescription())
            <p class="max-w-3xl leading-8 whitespace-pre-line text-ink-500">{{ $category->getDescription() }}</p>
        @endif
    </header>

    @if ($subcategories->isNotEmpty())
        <nav aria-label="زیردسته‌ها" class="scrollbar-none -mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <ul class="flex gap-2 md:flex-wrap">
                @foreach ($subcategories as $subcategory)
                    <li wire:key="subcategory-{{ $subcategory->getId() }}" class="shrink-0">
                        <a href="{{ route('category.show', $subcategory) }}" wire:navigate class="flex h-11 items-center gap-2 rounded-full border border-line-200 bg-white px-4 text-sm text-ink-900 hover:border-primary-300 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700">
                            <x-media::picture :media="$subcategory->getIcon()" slot-name="category-chip" alt="" class="size-6 object-contain" />
                            {{ $subcategory->getNameFa() }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <livewire:catalog::product-listing :category-id="$category->getId()" />
</div>
