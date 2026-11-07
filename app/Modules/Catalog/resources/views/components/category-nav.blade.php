{{--
    Category navigation under the header (roadmap B9.1, B8.1): a horizontal bar of top-level categories on tablet,
    and on desktop a mega-menu per category (columns = subcategories, links = their subcategories, at most 8 × 8,
    "مشاهده همه" per column). Opens on hover or click (a click never closes it, so hover-then-click works);
    closes on Escape, a click outside, or when the pointer leaves.
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
        x-on:keydown.escape.window="open = null"
        x-on:click.outside="open = null"
        x-on:mouseleave="open = null"
    >
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
                            x-transition.opacity.duration.150ms
                            class="absolute inset-x-0 top-full z-40 hidden border-t border-line-200 bg-white shadow-card xl:block"
                        >
                            <div class="mx-auto max-w-7xl space-y-4 px-8 py-6">
                                <a href="{{ $root->url() }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 font-bold text-primary-700 hover:underline">
                                    همه {{ $root->nameFa }}
                                    <x-ts-icon icon="chevron-left" class="size-4" />
                                </a>

                                <div class="grid grid-cols-4 gap-x-8 gap-y-6">
                                    @foreach (array_slice($root->children, 0, 8) as $column)
                                        <div class="space-y-1">
                                            <a href="{{ $column->url() }}" wire:navigate class="flex min-h-9 items-center font-bold text-ink-900 hover:text-primary-700">
                                                {{ $column->nameFa }}
                                            </a>
                                            @if ($column->children !== [])
                                                <ul>
                                                    @foreach (array_slice($column->children, 0, 8) as $link)
                                                        <li>
                                                            <a href="{{ $link->url() }}" wire:navigate class="flex min-h-9 items-center text-sm text-ink-500 hover:text-primary-700">
                                                                {{ $link->nameFa }}
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                <a href="{{ $column->url() }}" wire:navigate class="flex min-h-9 items-center text-sm text-primary-700 hover:underline">مشاهده همه</a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
