<?php

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Queries\ActiveBrandsQuery;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Brand index (roadmap B5 /b): an A–Z (الف–ی) jump bar and brand cards per letter. Logos arrive with S09.
 */
new #[Title('برندها')] class extends Component
{
    /**
     * @return Collection<string, list<Brand>>
     */
    #[Computed]
    public function groups(): Collection
    {
        return app(ActiveBrandsQuery::class)->grouped();
    }
};
?>

@use('App\Modules\Shared\Support\PersianDigits')

<div class="space-y-6">
    <x-content::banners place="brands_list" :priority="true" />

    <header class="space-y-1">
        <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">برندها</h1>
        <p class="text-sm text-ink-500">
            {{ PersianDigits::toPersian($this->groups->flatten()->count()) }} برند
        </p>
    </header>

    @if ($this->groups->isEmpty())
        <div class="rounded-card border border-line-200 bg-white px-6 py-12 text-center text-ink-500">
            هنوز برندی ثبت نشده است.
        </div>
    @else
        <nav aria-label="فهرست حروف" class="scrollbar-none sticky top-(--header-height) z-30 -mx-4 overflow-x-auto border-y border-line-200 bg-surface-50/95 px-4 py-2 backdrop-blur md:mx-0 md:rounded-card md:border md:bg-white">
            <ul class="flex gap-1 md:flex-wrap">
                @foreach ($this->groups->keys() as $letter)
                    <li>
                        <a href="#letter-{{ $loop->index }}" class="grid h-11 min-w-11 place-items-center rounded-md px-2 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                            {{ $letter }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @foreach ($this->groups as $letter => $brands)
            <section id="letter-{{ $loop->index }}" aria-labelledby="letter-heading-{{ $loop->index }}" class="scroll-mt-[calc(var(--header-height)+4.5rem)] space-y-3">
                <h2 id="letter-heading-{{ $loop->index }}" class="text-lg font-bold text-ink-900">{{ $letter }}</h2>

                <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                    @foreach ($brands as $brand)
                        <li wire:key="brand-{{ $brand->getId() }}">
                            <a href="{{ route('brands.show', $brand) }}" wire:navigate class="flex h-full min-h-24 flex-col justify-center gap-1 rounded-card border border-line-200 bg-white p-4 shadow-card transition hover:border-primary-300 focus-visible:outline-2 focus-visible:outline-primary-700">
                                @if ($brand->getLogo())
                                    <x-media::picture :media="$brand->getLogo()" slot-name="brand-logo" alt="" class="mb-2 h-12 w-24 object-contain md:h-15 md:w-30" />
                                @endif
                                <span class="line-clamp-2 font-bold text-ink-900">{{ $brand->getNameFa() }}</span>
                                @if ($brand->getNameEn())
                                    <span dir="ltr" class="truncate text-end text-sm text-ink-500">{{ $brand->getNameEn() }}</span>
                                @endif
                                @if ($brand->getCountryName())
                                    <span class="text-xs text-ink-500">{{ $brand->getCountryName() }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
