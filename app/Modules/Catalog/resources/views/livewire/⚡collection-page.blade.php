<?php

use App\Modules\Catalog\Models\ProductCollection;
use Livewire\Component;

/**
 * Collection page (roadmap S13b /collections/{slug}): the collection's products with the usual filters, in the
 * admin's order by default («پیشنهادی»). Hidden collections (inactive or outside their schedule) are not found.
 */
new class extends Component
{
    public ProductCollection $collection;

    public function mount(string $slug): void
    {
        $this->collection = ProductCollection::query()->visible()->with('media')->where(ProductCollection::COLUMN_SLUG, $slug)->firstOrFail();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->view()->title($this->collection->getNameFa());
    }
};
?>

<div class="space-y-6">
    <nav aria-label="مسیر صفحه" class="text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700">خانه</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('collections.index') }}" wire:navigate class="hover:text-primary-700">کالکشن‌ها</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-ink-900">{{ $collection->getNameFa() }}</li>
        </ol>
    </nav>

    <x-content::banners place="collection" :place-id="$collection->getId()" :priority="true" />

    <header class="space-y-1">
        <h1 class="flex items-center gap-2 text-2xl font-bold text-ink-900 xl:text-3xl">
            @if ($collection->getIcon())
                <x-media::picture :media="$collection->getIcon()" slot-name="category-chip" alt="" :priority="true" class="size-7 object-contain" />
            @endif
            {{ $collection->getNameFa() }}
        </h1>
        @if ($collection->getDescription())
            <p class="text-ink-500">{{ $collection->getDescription() }}</p>
        @endif
    </header>

    <livewire:catalog::product-listing :collection-id="$collection->getId()" />
</div>
