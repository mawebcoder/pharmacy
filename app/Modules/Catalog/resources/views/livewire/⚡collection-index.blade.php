<?php

use App\Modules\Catalog\Models\ProductCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Visible collections that have at least one product for sale (roadmap S13b /collections), in the admin's order,
 * with their small icons and how many such products they hold.
 */
new #[Title('کالکشن‌ها')] class extends Component
{
    /**
     * @return Collection<int, ProductCollection>
     */
    #[Computed]
    public function collections(): Collection
    {
        return ProductCollection::query()
            ->visible()
            ->with('media')
            ->whereHas('products', fn (Builder $products) => $products->active())
            ->withCount(['products' => fn (Builder $products) => $products->active()])
            ->orderBy(ProductCollection::COLUMN_SORT)
            ->orderBy(ProductCollection::COLUMN_ID)
            ->get();
    }
};
?>

@use('App\Modules\Shared\Support\PersianDigits')

<div class="space-y-4">
    <x-content::banners place="collections_list" :priority="true" />

    <h1 class="text-2xl font-bold text-ink-900">کالکشن‌ها</h1>

    @if ($this->collections->isEmpty())
        <div class="rounded-card border border-line-200 bg-white px-6 py-12 text-center text-ink-500">
            هنوز کالکشنی ثبت نشده است.
        </div>
    @else
        <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->collections as $collection)
                <li wire:key="collection-{{ $collection->getId() }}">
                    <a href="{{ route('collections.show', $collection) }}" wire:navigate class="flex min-h-16 items-center gap-3 rounded-card border border-line-200 bg-white px-4 py-3 hover:border-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-50">
                            @if ($collection->getIcon())
                                <x-media::picture :media="$collection->getIcon()" slot-name="category-chip" alt="" class="size-6 object-contain" />
                            @else
                                <x-ts-icon icon="squares-2x2" class="size-5 text-primary-700" />
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-ink-900">{{ $collection->getNameFa() }}</span>
                            <span class="block text-xs text-ink-500">{{ PersianDigits::toPersian($collection->products_count) }} محصول</span>
                        </span>
                        <x-ts-icon icon="chevron-left" class="size-5 text-ink-500" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
