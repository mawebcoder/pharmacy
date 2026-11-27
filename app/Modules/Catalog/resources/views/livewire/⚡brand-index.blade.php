<?php

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Queries\ActiveBrandsQuery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Brand index (roadmap B5 /brands): every active brand in one grid, sorted by Persian name, without the letter bar or
 * letter headings; 20 at first and 20 more as the shopper scrolls near the end, no pages (owner, 2026-11-27).
 */
new #[Title('برندها')] class extends Component
{
    public const int PER_LOAD = 20;

    public int $shown = self::PER_LOAD;

    public function loadMore(): void
    {
        $this->shown = min($this->shown + self::PER_LOAD, count($this->brands));
    }

    /**
     * @return list<Brand>
     */
    #[Computed]
    public function brands(): array
    {
        return app(ActiveBrandsQuery::class)->sorted();
    }
};
?>

@use('App\Modules\Shared\Support\PersianDigits')

<div class="space-y-6">
    <x-content::banners place="brands_list" :priority="true" />

    <header class="space-y-1">
        <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">برندها</h1>
        <p class="text-sm text-ink-500">
            {{ PersianDigits::toPersian(count($this->brands)) }} برند
        </p>
    </header>

    @if ($this->brands === [])
        <div class="rounded-card border border-line-200 bg-card px-6 py-12 text-center text-ink-500">
            هنوز برندی ثبت نشده است.
        </div>
    @else
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach (array_slice($this->brands, 0, $shown) as $brand)
                <li wire:key="brand-{{ $brand->getId() }}">
                    <a href="{{ route('brands.show', $brand) }}" wire:navigate class="group flex h-full flex-col items-center gap-1 rounded-card p-4 text-center focus-visible:outline-2 focus-visible:outline-primary-700">
                        {{-- No border or card around the tile; the logo (padded to a white square on upload) with rounded
                             corners, not a circle (owner, 2026-11-27); the first two letters without a logo. --}}
                        @if ($brand->getLogo())
                            <x-media::picture :media="$brand->getLogo()" slot-name="brand-logo" alt="" class="mb-2 size-28 rounded-card object-contain md:size-32" />
                        @else
                            <span class="mb-2 grid size-28 place-items-center rounded-card bg-surface-50 text-2xl font-bold text-ink-500 md:size-32" aria-hidden="true">{{ mb_substr($brand->getNameFa(), 0, 2) }}</span>
                        @endif
                        <span class="line-clamp-2 font-bold text-ink-900 group-hover:text-primary-700">{{ $brand->getNameFa() }}</span>
                        @if ($brand->getNameEn())
                            <span dir="ltr" class="w-full truncate text-sm text-ink-500">{{ $brand->getNameEn() }}</span>
                        @endif
                        @if ($brand->getCountryName())
                            <span class="text-xs text-ink-500">{{ $brand->getCountryName() }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- The next 20 load when these placeholder tiles come near the screen; keyed by the count so each load is
             watched again. --}}
        @if ($shown < count($this->brands))
            <div wire:key="brands-more-{{ $shown }}" x-data x-intersect.margin.600px="$wire.loadMore()">
                <ul class="grid animate-pulse grid-cols-2 gap-3 motion-reduce:animate-none md:grid-cols-3 xl:grid-cols-5" aria-hidden="true">
                    @foreach (range(1, 5) as $placeholder)
                        <li @class(['flex-col items-center gap-2 p-4', 'flex' => $placeholder <= 2, 'hidden md:flex' => $placeholder === 3, 'hidden xl:flex' => $placeholder > 3])>
                            <div class="size-28 rounded-card bg-line-200 md:size-32"></div>
                            <div class="h-4 w-2/3 rounded bg-line-200"></div>
                            <div class="h-3 w-1/2 rounded bg-line-200"></div>
                        </li>
                    @endforeach
                </ul>
                <p class="sr-only" role="status" wire:loading wire:target="loadMore">در حال نمایش برندهای بیشتر</p>
            </div>
        @endif
    @endif
</div>
