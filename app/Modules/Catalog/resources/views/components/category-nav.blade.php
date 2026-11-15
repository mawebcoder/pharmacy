{{--
    Category navigation under the header (roadmap B9.1, B8.1).
    Tablet: a horizontal bar of the categories (links).
    Desktop (owner, 2026-11-15, like Digikala's): «دسته‌بندی کالاها» opens one panel — the categories with their icons in
    a column on the start side, and the hovered one's subcategories as tiles with icons beside it, under «همه محصولات …».
    After the trigger come the site links ($links: «همه کالاها», «برندها» straight to /brands, and pages such as «درباره
    ما» and «تماس با ما» once they are live). Opens on hover or click (a click never closes it, so hover-then-click
    works); closes on Escape, a click outside, a click on a link, scrolling, or when the pointer leaves. While it is open
    the page below the header is dimmed and blurred like behind the search panel.
    Mobile uses the "دسته‌ها" tab (/categories) instead. The tree comes from the cache (CategoryTree).
    <x-catalog::category-nav :links="[['label' => 'برندها', 'href' => route('brands.index'), 'icon' => 'tag'], …]" />
--}}
@props(['links' => []])

@use('App\Modules\Catalog\Data\CategoryNode')
@use('App\Modules\Catalog\Models\Category')
@use('App\Modules\Catalog\Models\Subcategory')
@use('App\Modules\Catalog\Queries\CategoryTree')

@php
    $tree = app(CategoryTree::class);
    $roots = $tree->roots();
    // The desktop panel lists only subcategories with a product for sale, and only categories that have one (owner,
    // 2026-11-15); the tablet bar keeps every category. Two small queries, which also bring the icons (the tree cache
    // holds no media or stock).
    $subcategoryIcons = $roots === [] ? [] : Subcategory::query()->with('media')->whereKey($tree->visibleSubcategoryIds())->withProductsForSale()->get()
        ->mapWithKeys(fn (Subcategory $subcategory): array => [$subcategory->getId() => $subcategory->getIcon()])->all();
    $menuRoots = array_values(array_filter(array_map(
        fn (CategoryNode $root): CategoryNode => new CategoryNode($root->id, $root->nameFa, $root->slug, array_values(array_filter($root->children, fn (CategoryNode $child): bool => array_key_exists($child->id, $subcategoryIcons)))),
        $roots,
    ), fn (CategoryNode $root): bool => $root->children !== []));
    $categoryIcons = $menuRoots === [] ? [] : Category::query()->with('media')->whereKey(array_map(fn (CategoryNode $root): int => $root->id, $menuRoots))->get()
        // The tiny menu icon, else the category's own icon (each with its image slot).
        ->mapWithKeys(fn (Category $category): array => [$category->getId() => match (true) {
            $category->getMenuIcon() !== null => [$category->getMenuIcon(), 'category-menu-icon'],
            $category->getIcon() !== null => [$category->getIcon(), 'category-chip'],
            default => null,
        }])->all();
    $linkClass = 'flex h-11 shrink-0 items-center gap-1.5 rounded-md px-3 text-sm text-ink-900 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700';
@endphp

@if ($roots !== [])
    <nav
        aria-label="دسته‌بندی محصولات"
        class="relative hidden border-t border-line-200 md:block"
        x-data="{ open: false, active: {{ $menuRoots[0]->id ?? 0 }} }"
        x-on:scroll.window.passive="open = false"
        x-on:keydown.escape.window="open = false"
        x-on:click.outside="open = false"
        x-on:mouseleave="open = false"
        x-on:click="$event.target.closest('a[href]') && (open = false)"
    >
        {{-- Inside the nav so moving onto it from the panel is not a mouseleave; the pointer on it or a click closes the
             panel. --}}
        <x-layout.header-backdrop show="open" close="open = false" x-on:mouseenter="open = false" class="hidden xl:block" />

        <div class="scrollbar-none mx-auto flex h-11 max-w-7xl items-center gap-1 overflow-x-auto px-6 xl:overflow-visible xl:px-8">
            {{-- Tablet: the categories as links. --}}
            <ul class="flex items-center gap-1 xl:hidden">
                @foreach ($roots as $root)
                    <li class="shrink-0"><a href="{{ $root->url() }}" wire:navigate class="{{ $linkClass }}">{{ $root->nameFa }}</a></li>
                @endforeach
            </ul>

            {{-- Desktop: the trigger (when a category has something for sale), then the site links. --}}
            @if ($menuRoots !== [])
                <button
                    type="button"
                    class="relative hidden h-11 shrink-0 items-center gap-2 px-1 text-sm font-bold text-ink-900 after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:rounded-full after:bg-primary-700 after:opacity-0 after:transition-opacity hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700 xl:flex"
                    x-on:mouseenter="open = true"
                    x-on:click="open = true"
                    x-bind:class="open && 'text-primary-700 after:opacity-100'"
                    x-bind:aria-expanded="open"
                    aria-controls="mega-menu"
                >
                    <x-ts-icon icon="bars-3" class="size-5" />
                    دسته‌بندی کالاها
                </button>
            @endif
            @if ($links !== [])
                <span class="mx-3 hidden h-5 w-px bg-line-200 xl:block" aria-hidden="true"></span>
                <ul class="hidden items-center gap-1 xl:flex" x-on:mouseenter="open = false">
                    @foreach ($links as $link)
                        <li>
                            <a href="{{ $link['href'] }}" wire:navigate class="{{ $linkClass }} text-ink-500">
                                @isset($link['icon'])
                                    <x-ts-icon :icon="$link['icon']" class="size-5" />
                                @endisset
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Desktop panel: categories on the start side, the hovered one's subcategories beside them. Fades in, leaves
             at once. --}}
        <div
            id="mega-menu"
            x-show="open"
            x-cloak
            x-transition:enter="transition-opacity duration-150 ease-out motion-reduce:transition-none"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="absolute inset-x-0 top-full z-40 hidden xl:block"
        >
            <div class="mx-auto max-w-7xl px-8">
                <div class="flex max-h-[70vh] min-h-80 overflow-hidden rounded-b-card border border-t-0 border-line-200 bg-card shadow-lg">
                    <ul class="w-60 shrink-0 overflow-y-auto border-e border-line-200 bg-surface-50 py-2">
                        @foreach ($menuRoots as $root)
                            <li>
                                <a
                                    href="{{ $root->url() }}"
                                    wire:navigate
                                    x-on:mouseenter="active = {{ $root->id }}"
                                    x-on:focus="active = {{ $root->id }}"
                                    x-bind:class="active === {{ $root->id }} ? 'bg-card font-bold text-primary-700' : 'text-ink-900'"
                                    class="flex min-h-12 items-center gap-3 px-4 text-sm hover:text-primary-700 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700"
                                >
                                    <span class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-card">
                                        @if ($categoryIcons[$root->id] ?? null)
                                            <x-media::picture :media="$categoryIcons[$root->id][0]" :slot-name="$categoryIcons[$root->id][1]" alt="" class="size-6 object-contain" />
                                        @else
                                            <x-ts-icon icon="squares-2x2" class="size-5 text-ink-500" />
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1 truncate">{{ $root->nameFa }}</span>
                                    <x-ts-icon icon="chevron-left" class="size-4 shrink-0 opacity-0" x-bind:class="active === {{ $root->id }} && 'opacity-100'" />
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @foreach ($menuRoots as $root)
                        <div x-show="active === {{ $root->id }}" class="min-w-0 flex-1 overflow-y-auto p-6">
                            <a href="{{ $root->url() }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm font-bold text-primary-700 hover:underline">
                                همه محصولات {{ $root->nameFa }}
                                <x-ts-icon icon="chevron-left" class="size-4" />
                            </a>

                            @if ($root->children !== [])
                                <ul class="mt-4 grid grid-cols-5 gap-3">
                                    @foreach ($root->children as $subcategory)
                                        <li>
                                            <a href="{{ $subcategory->url() }}" wire:navigate class="flex h-full flex-col items-center gap-2 rounded-card border border-line-200 px-2 py-4 text-center text-sm text-ink-900 transition-colors hover:border-primary-700 hover:text-primary-700 motion-reduce:transition-none">
                                                <span class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-full bg-surface-50">
                                                    @if ($subcategoryIcons[$subcategory->id] ?? null)
                                                        <x-media::picture :media="$subcategoryIcons[$subcategory->id]" slot-name="category-icon" alt="" class="size-10 object-contain" />
                                                    @else
                                                        <x-ts-icon icon="squares-2x2" class="size-7 text-ink-500" />
                                                    @endif
                                                </span>
                                                <span class="line-clamp-2 leading-6">{{ $subcategory->nameFa }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </nav>
@endif
