{{--
    Jalali (Shamsi) date picker. Binds a Gregorian Y-m-d string, so the server side stays standard:
    <x-jalali-date label="تاریخ تحویل" wire:model.live="deliveryDate" :min="now()" />
--}}
@props([
    'label' => null,
    'hint' => null,
    'placeholder' => 'انتخاب تاریخ',
    'name' => null,
    'value' => null,
    'min' => null,
    'max' => null,
    'clearable' => true,
    'disabled' => false,
])

@php
    $model = $attributes->wire('model')->value();
    $errorKey = $model ?: $name;
    // Stable across Livewire re-renders so morphing keeps the label/input pairing.
    $id = $attributes->get('id', 'jalali-date-'.($errorKey ? \Illuminate\Support\Str::slug($errorKey) : \Illuminate\Support\Str::random(8)));
    // Dates are compared as calendar days in the display timezone (Asia/Tehran), not UTC.
    $displayTimezone = config('app.display_timezone');
    $toIso = fn ($date) => $date instanceof \DateTimeInterface
        ? \Illuminate\Support\Carbon::instance($date)->setTimezone($displayTimezone)->format('Y-m-d')
        : $date;
    $config = [
        'value' => $toIso($value),
        'today' => now($displayTimezone)->toDateString(),
        'min' => $toIso($min),
        'max' => $toIso($max),
    ];
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $error = $errorKey ? $errorBag->first($errorKey) : null;
    $invalid = filled($error);
@endphp

<div
    x-data="jalaliDate(@js($config))"
    x-modelable="value"
    x-on:keydown.escape="close()"
    x-on:click.outside="close()"
    {{ $attributes->except('id')->class('relative') }}
>
    @if ($label)
        <label for="{{ $id }}" @class(['mb-1 block text-sm font-medium', 'text-gray-600' => ! $invalid, 'text-red-600' => $invalid])>{{ $label }}</label>
    @endif

    <div @class([
        'flex items-center rounded-md bg-white ring-1 focus-within:ring-2',
        'ring-gray-200 focus-within:ring-primary-600' => ! $invalid,
        'ring-red-300 focus-within:ring-red-500' => $invalid,
        'bg-gray-100!' => $disabled,
    ])>
        <span class="ps-3 text-gray-400">
            <x-ts-icon icon="calendar-days" class="size-5" />
        </span>

        <input
            id="{{ $id }}"
            type="text"
            readonly
            @disabled($disabled)
            x-bind:value="display"
            x-on:click="toggle()"
            x-on:keydown.enter.prevent="toggle()"
            x-on:keydown.space.prevent="toggle()"
            placeholder="{{ $placeholder }}"
            aria-haspopup="dialog"
            x-bind:aria-expanded="open"
            class="w-full cursor-pointer border-0 bg-transparent px-2 py-1.5 text-gray-700 placeholder:text-gray-400 focus:ring-0 focus:outline-hidden disabled:cursor-not-allowed sm:text-sm sm:leading-6"
        >

        @if ($clearable && ! $disabled)
            <button type="button" x-show="value" x-cloak x-on:click="clear()" class="pe-3 text-gray-400 hover:text-red-500" aria-label="پاک کردن تاریخ">
                <x-ts-icon icon="x-mark" class="size-5" />
            </button>
        @endif
    </div>

    @if ($name)
        <input type="hidden" name="{{ $name }}" x-bind:value="value ?? ''">
    @endif

    {{-- Popup --}}
    <div
        x-show="open"
        x-cloak
        x-transition.origin.top
        role="dialog"
        aria-label="تقویم"
        class="absolute start-0 z-50 mt-2 w-72 rounded-xl bg-white p-3 shadow-lg ring-1 ring-gray-200"
    >
        {{-- Header: in RTL the first button sits on the right, so "previous" points right. --}}
        <div class="mb-3 flex items-center justify-between">
            <button type="button" x-show="view !== 'months'" x-on:click="view === 'days' ? move(-1) : yearPage -= 12" class="rounded-full p-1 text-gray-600 hover:bg-gray-100" aria-label="قبلی">
                <x-ts-icon icon="chevron-right" class="size-5" />
            </button>
            <button type="button" x-show="view === 'months'" x-on:click="cursor.jy--" class="rounded-full p-1 text-gray-600 hover:bg-gray-100" aria-label="سال قبل">
                <x-ts-icon icon="chevron-right" class="size-5" />
            </button>

            <div class="flex items-center gap-1 text-sm font-bold text-gray-800">
                <button type="button" x-show="view === 'days'" x-on:click="view = 'months'" x-text="months[cursor.jm - 1]" class="rounded-md px-2 py-1 hover:bg-gray-100"></button>
                <button type="button" x-show="view !== 'years'" x-on:click="showYears()" x-text="fa(cursor.jy)" class="rounded-md px-2 py-1 font-normal text-gray-600 hover:bg-gray-100"></button>
                <span x-show="view === 'years'" x-text="fa(yearPage) + ' – ' + fa(yearPage + 11)" class="px-2 py-1"></span>
            </div>

            <button type="button" x-show="view !== 'months'" x-on:click="view === 'days' ? move(1) : yearPage += 12" class="rounded-full p-1 text-gray-600 hover:bg-gray-100" aria-label="بعدی">
                <x-ts-icon icon="chevron-left" class="size-5" />
            </button>
            <button type="button" x-show="view === 'months'" x-on:click="cursor.jy++" class="rounded-full p-1 text-gray-600 hover:bg-gray-100" aria-label="سال بعد">
                <x-ts-icon icon="chevron-left" class="size-5" />
            </button>
        </div>

        {{-- Days --}}
        <div x-show="view === 'days'">
            <div class="mb-1 grid grid-cols-7 text-center text-xs font-medium text-gray-400">
                <template x-for="(day, index) in weekdays" :key="day">
                    <span x-text="day.charAt(0)" x-bind:title="day" x-bind:class="index === 6 && 'text-red-400'"></span>
                </template>
            </div>

            <div class="grid grid-cols-7 gap-y-1">
                <template x-for="cell in days" :key="cell.key">
                    <div class="flex justify-center">
                        <template x-if="! cell.blank">
                            <button
                                type="button"
                                x-on:click="pick(cell.iso)"
                                x-bind:disabled="cell.disabled"
                                x-text="fa(cell.jd)"
                                x-bind:aria-pressed="cell.selected"
                                x-bind:class="{
                                    'bg-primary-600 text-white hover:bg-primary-700': cell.selected,
                                    'text-red-500': cell.friday && ! cell.selected,
                                    'ring-1 ring-primary-500 font-bold text-primary-700': cell.today && ! cell.selected,
                                    'hover:bg-primary-50': ! cell.selected,
                                }"
                                class="flex size-8 items-center justify-center rounded-full text-sm text-gray-700 transition disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-transparent"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Months --}}
        <div x-show="view === 'months'" class="grid grid-cols-3 gap-2">
            <template x-for="(month, index) in months" :key="month">
                <button
                    type="button"
                    x-on:click="pickMonth(index + 1)"
                    x-bind:disabled="monthDisabled(index + 1)"
                    x-text="month"
                    x-bind:class="cursor.jm === index + 1 ? 'bg-primary-600 text-white' : 'text-gray-700 hover:bg-primary-50'"
                    class="rounded-lg py-2 text-sm disabled:cursor-not-allowed disabled:text-gray-300"
                ></button>
            </template>
        </div>

        {{-- Years --}}
        <div x-show="view === 'years'" class="grid grid-cols-3 gap-2">
            <template x-for="year in years" :key="year">
                <button
                    type="button"
                    x-on:click="pickYear(year)"
                    x-bind:disabled="yearDisabled(year)"
                    x-text="fa(year)"
                    x-bind:class="cursor.jy === year ? 'bg-primary-600 text-white' : 'text-gray-700 hover:bg-primary-50'"
                    class="rounded-lg py-2 text-sm disabled:cursor-not-allowed disabled:text-gray-300"
                ></button>
            </template>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
            <button type="button" x-on:click="pickToday()" x-bind:disabled="todayDisabled" class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-600 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-gray-100">امروز</button>
            @if ($clearable)
                <button type="button" x-on:click="clear()" class="text-sm text-gray-500 hover:text-red-500">پاک کردن</button>
            @endif
        </div>
    </div>

    @if ($invalid)
        <span class="mt-1 block text-sm font-medium text-red-500">{{ $error }}</span>
    @elseif ($hint)
        <span class="mt-1 block text-sm text-gray-500">{{ $hint }}</span>
    @endif
</div>
