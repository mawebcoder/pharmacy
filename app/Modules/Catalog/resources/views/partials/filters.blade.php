{{--
    Filter groups of a product list (roadmap B9.3): collapsible groups with counts; long lists get a search box.
    Used in the desktop sidebar and in the phone/tablet slide. Changes apply at once (wire:model.live).
    Variables: $result (ListingResult), $idPrefix (unique per copy of the panel).
--}}
@use('App\Modules\Catalog\Livewire\ProductListing')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $count = fn (int $value): string => PersianDigits::toPersian($value);
@endphp

<div class="divide-y divide-line-200">
    @foreach ($result->facets as $group)
        <details class="group py-3" open>
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-bold text-ink-900">
                {{ $group->label }}
                <x-ts-icon icon="chevron-down" class="size-4 text-ink-500 transition group-open:rotate-180 motion-reduce:transition-none" />
            </summary>

            <div x-data="{ search: '' }" class="space-y-1 pt-1">
                @if (count($group->options) > 8)
                    <label class="sr-only" for="{{ $idPrefix }}-search-{{ $group->key }}">جستجو در {{ $group->label }}</label>
                    <input
                        id="{{ $idPrefix }}-search-{{ $group->key }}"
                        type="search"
                        x-model="search"
                        placeholder="جستجو در {{ $group->label }}"
                        class="mb-2 h-10 w-full rounded-md border-0 bg-surface-50 px-3 text-sm ring-1 ring-line-200 focus:ring-2 focus:ring-primary-700"
                    >
                @endif

                <ul class="max-h-64 space-y-1 overflow-y-auto">
                    @foreach ($group->options as $option)
                        @php($isBrand = $group->key === 'brand')
                        @php($isChecked = $isBrand ? in_array($option->id, $this->brands) : in_array($option->id, $this->selectedAttributeValues($group->key), true))
                        <li x-show="search === '' || @js($option->label).includes(search)" wire:key="{{ $idPrefix }}-{{ $group->key }}-{{ $option->id }}-{{ (int) $isChecked }}">
                            <label class="flex min-h-10 cursor-pointer items-center gap-3 rounded-md px-1 text-sm text-ink-900 hover:bg-surface-50">
                                <input
                                    type="checkbox"
                                    value="{{ $option->id }}"
                                    @if ($isBrand) wire:model.live="brands" @else wire:click="toggleAttributeValue(@js($group->key), {{ $option->id }})" @checked($isChecked) @endif
                                    class="size-5 rounded border-line-200 text-primary-700 focus:ring-primary-700"
                                >
                                <span class="flex-1">{{ $option->label }}</span>
                                <span class="text-xs text-ink-500">{{ $count($option->count) }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        </details>
    @endforeach

    <details class="group py-3" open>
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-bold text-ink-900">
            قیمت (تومان)
            <x-ts-icon icon="chevron-down" class="size-4 text-ink-500 transition group-open:rotate-180 motion-reduce:transition-none" />
        </summary>
        <div class="grid grid-cols-2 gap-2 pt-1">
            <label class="space-y-1 text-xs text-ink-500">
                از
                <input type="number" min="0" inputmode="numeric" wire:model.live.debounce.700ms="minPrice"
                    placeholder="{{ $result->priceFloor === null ? '' : PersianDigits::toPersian(number_format(intdiv($result->priceFloor, 10))) }}"
                    class="h-10 w-full rounded-md border-0 bg-surface-50 px-3 text-sm text-ink-900 ring-1 ring-line-200 focus:ring-2 focus:ring-primary-700">
            </label>
            <label class="space-y-1 text-xs text-ink-500">
                تا
                <input type="number" min="0" inputmode="numeric" wire:model.live.debounce.700ms="maxPrice"
                    placeholder="{{ $result->priceCeiling === null ? '' : PersianDigits::toPersian(number_format(intdiv($result->priceCeiling, 10))) }}"
                    class="h-10 w-full rounded-md border-0 bg-surface-50 px-3 text-sm text-ink-900 ring-1 ring-line-200 focus:ring-2 focus:ring-primary-700">
            </label>
        </div>
    </details>

    <details class="group py-3" open>
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-bold text-ink-900">
            تخفیف
            <x-ts-icon icon="chevron-down" class="size-4 text-ink-500 transition group-open:rotate-180 motion-reduce:transition-none" />
        </summary>
        <div class="flex flex-wrap gap-2 pt-1">
            @foreach (ProductListing::DISCOUNT_STEPS as $step)
                <label class="cursor-pointer">
                    <input type="radio" name="{{ $idPrefix }}-discount" value="{{ $step }}" wire:model.live="discount" class="peer sr-only">
                    <span class="flex h-9 items-center rounded-full border border-line-200 px-3 text-sm text-ink-900 peer-checked:border-accent-500 peer-checked:bg-accent-500 peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-primary-700">
                        {{ PersianDigits::toPersian($step) }}٪ و بیشتر
                    </span>
                </label>
            @endforeach
        </div>
    </details>

    <div class="space-y-1 py-3">
        @foreach (['inStock' => ['فقط کالاهای موجود', 'in_stock'], 'nearExpiry' => ['زودمصرف', 'near_expiry'], 'damagedBox' => ['کج‌جعبه', 'damaged_box']] as $property => [$label, $countKey])
            <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 text-sm text-ink-900">
                <span>{{ $label }} @unless ($result->degraded)<span class="text-xs text-ink-500">({{ $count($result->flagCounts[$countKey]) }})</span>@endunless</span>
                <input type="checkbox" wire:model.live="{{ $property }}" class="size-5 rounded border-line-200 text-primary-700 focus:ring-primary-700">
            </label>
        @endforeach
    </div>
</div>
