{{--
    Category navigation under the header (roadmap B9.1, B8.1): a horizontal bar of top-level categories on tablet,
    and on desktop a mega-menu per category: «همه …» and its subcategories as a grid of links (categories have two
    levels; owner, 2026-11-15). Opens on hover or click (a click never closes it, so hover-then-click works);
    closes on Escape, a click outside, or when the pointer leaves. A menu fades in but leaves at once, so moving to the
    next category never shows two menus at the same time (owner, 2026-11-15). While one is open the page below the header is
    dimmed and blurred like behind the search panel (owner, 2026-11-15).
    Mobile uses the "دسته‌ها" tab (/categories) instead. The tree comes from the cache (CategoryTree).
--}}
@use('App\Modules\Catalog\Queries\CategoryTree')

@php
    $roots = app(CategoryTree::class)->roots();
@endphp

@if ($roots !== [])
    <nav
        aria-label="دسته‌بندی محصولات"
        class="relative hidden border-t border-line-200 md:block"
        x-data="{ open: null }"
        x-on:scroll.window.passive="open = null"
        x-on:keydown.escape.window="open = null"
        x-on:click.outside="open = null"
        x-on:mouseleave="open = null"
        x-on:click="$event.target.closest('a[href]') && (open = null)"
    >
        {{-- Inside the nav so moving onto it from a menu is not a mouseleave; the pointer on it or a click closes the menu.
             Scrolling closes the menu too. --}}
        <x-layout.header-backdrop show="open !== null" close="open = null" x-on:mouseenter="open = null" class="hidden xl:block" />

        <ul class="scrollbar-none mx-auto flex h-11 max-w-7xl items-center gap-1 overflow-x-auto px-6 xl:overflow-visible xl:px-8">
            @foreach ($roots as $root)
                <li class="shrink-0">
                    <a
                        href="{{ $root->url() }}"
                        wire:navigate
                        @class([
                            'flex h-11 items-center rounded-md px-3 text-sm text-ink-900 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700',
                            'xl:hidden' => $root->children !== [],
                        ])
                    >
                        {{ $root->nameFa }}
                    </a>

                    @if ($root->children !== [])
                        <button
                            type="button"
                            class="hidden h-11 items-center gap-1 rounded-md px-3 text-sm text-ink-900 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700 xl:flex"
                            x-on:mouseenter="open = {{ $root->id }}"
                            x-on:click="open = {{ $root->id }}"
                            x-bind:class="open === {{ $root->id }} && 'text-primary-700'"
                            x-bind:aria-expanded="open === {{ $root->id }}"
                            aria-controls="mega-menu-{{ $root->id }}"
                        >
                            {{ $root->nameFa }}
                            <x-ts-icon icon="chevron-down" class="size-4 transition motion-reduce:transition-none" x-bind:class="open === {{ $root->id }} && 'rotate-180'" />
                        </button>

                        <div
                            id="mega-menu-{{ $root->id }}"
                            x-show="open === {{ $root->id }}"
                            x-cloak
                            x-transition:enter="transition-opacity duration-150 ease-out motion-reduce:transition-none"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            class="absolute inset-x-0 top-full z-40 hidden border-t border-line-200 bg-card shadow-card xl:block"
                        >
                            <div class="mx-auto max-w-7xl space-y-4 px-8 py-6">
                                <a href="{{ $root->url() }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 font-bold text-primary-700 hover:underline">
                                    همه {{ $root->nameFa }}
                                    <x-ts-icon icon="chevron-left" class="size-4" />
                                </a>

                                <ul class="grid grid-cols-4 gap-x-8 gap-y-1">
                                    @foreach (array_slice($root->children, 0, 24) as $subcategory)
                                        <li>
                                            <a href="{{ $subcategory->url() }}" wire:navigate class="flex min-h-10 items-center text-ink-900 hover:text-primary-700">
                                                {{ $subcategory->nameFa }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
