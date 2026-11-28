{{--
    The phone and tablet menu (roadmap S29; owner 2026-11-17): the «منو» menu from the admin («منوها»), opened by the
    «منو» button in the header below 1280 px. Items without a live target are left out (MenuLinks).
--}}
@php
    $menu = app(\App\Modules\Content\Queries\MenuLinks::class)->for(\App\Modules\Content\Enums\MenuLocation::Mobile);
@endphp

@if ($menu['links'] !== [])
    {{-- The slide teleports itself to <body> from a <template>, which Alpine only runs inside a component: without
         this root it opened only when Alpine happened to start from <body> (the «منو» button sometimes did nothing). --}}
    <div x-data>
        <x-ts-slide id="site-menu" title="منو" size="sm">
            <nav aria-label="منوی سایت">
                <ul class="-mx-2 space-y-1">
                    @foreach ($menu['links'] as $link)
                        <li>
                            <a
                                href="{{ $link['href'] }}"
                                @if ($link['newTab']) target="_blank" rel="noopener" @else wire:navigate @endif
                                x-on:click="$tsui.close.slide('site-menu')"
                                class="flex min-h-12 items-center gap-3 rounded-card px-2 text-sm font-medium text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700"
                            >
                                @if ($link['icon'] !== null)
                                    <x-ts-icon :icon="$link['icon']" class="size-5 text-primary-700" />
                                @endif
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </x-ts-slide>
    </div>
@endif
