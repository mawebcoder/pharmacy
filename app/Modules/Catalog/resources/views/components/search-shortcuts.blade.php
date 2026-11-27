{{--
    Category and brand rows of the search panel (owner, 2026-11-15): round icons and logos like the home page widgets,
    smaller, each row swipeable. Matching ones while typing; the admin's pinned ones (SearchShortcuts) in the empty
    box and when nothing matches. Links are options, so the arrow keys reach them.
    <x-catalog::search-shortcuts :categories="$categories" :brands="$brands" categories-title="دسته‌بندی‌ها" />
--}}
@props([
    'categories',
    'brands',
    'categoriesTitle' => 'دسته‌بندی‌ها',
    'brandsTitle' => 'برندها',
    'headingClass' => 'text-sm font-bold text-ink-900',
])

@foreach ([['category', $categories, $categoriesTitle], ['brand', $brands, $brandsTitle]] as [$type, $items, $title])
    @if ($items->isNotEmpty())
        <section aria-label="{{ $title }}" {{ $attributes }}>
            <p class="mb-1 {{ $headingClass }}">{{ $title }}</p>
            <ul class="scrollbar-none -mx-1 flex overflow-x-auto px-1">
                @foreach ($items as $item)
                    <li wire:key="search-{{ $type }}-{{ class_basename($item) }}-{{ $item->getId() }}" class="w-20 shrink-0">
                        <a
                            role="option"
                            href="{{ $type === 'category' ? $item->url() : route('brands.show', $item->getSlug()) }}"
                            wire:navigate
                            class="flex flex-col items-center gap-1.5 rounded-card p-1.5 text-center hover:bg-surface-50 focus:bg-primary-50 focus:outline-none"
                        >
                            @if ($type === 'category')
                                <span class="grid size-14 place-items-center rounded-full bg-primary-50">
                                    @if ($item->getIcon())
                                        <x-media::picture :media="$item->getIcon()" slot-name="category-icon" alt="" class="size-9 object-contain" />
                                    @else
                                        <x-ts-icon icon="squares-2x2" class="size-6 text-primary-700" />
                                    @endif
                                </span>
                            @else
                                <span class="grid size-14 place-items-center overflow-hidden rounded-full bg-media p-2">
                                    @if ($item->getLogo())
                                        <x-media::picture :media="$item->getLogo()" slot-name="brand-logo" alt="" class="size-full object-contain" />
                                    @else
                                        <span class="text-xs font-bold text-ink-500">{{ mb_substr($item->getNameFa(), 0, 2) }}</span>
                                    @endif
                                </span>
                            @endif
                            <span class="line-clamp-2 text-xs leading-4 text-ink-900">{{ $item->getNameFa() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endforeach
