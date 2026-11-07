{{--
    Nutrient / need chips (roadmap B9.2 item 2, B10 chip): icon + name, linking to /t/{slug}.
    Swipe on mobile, wrap from tablet up. Without :tags it shows the featured tags (home page), at most 12.
    <x-catalog::tag-chips />  ·  <x-catalog::tag-chips :tags="$tags" :current="$tag" label="برچسب‌ها" />
--}}
@props([
    'tags' => null,
    'current' => null,
    'label' => 'خرید بر اساس نیاز',
])

@use('App\Modules\Catalog\Models\Tag')

@php
    $tags ??= Tag::query()->featured()->with('media')->limit(12)->get();
@endphp

@if ($tags->isNotEmpty())
    <nav aria-label="{{ $label }}" {{ $attributes->class('scrollbar-none -mx-4 overflow-x-auto px-4 md:mx-0 md:px-0') }}>
        <ul class="flex gap-2 md:flex-wrap">
            @foreach ($tags as $tag)
                @php($isCurrent = $current?->is($tag))
                <li wire:key="tag-chip-{{ $tag->getId() }}" class="shrink-0">
                    <a
                        href="{{ route('tags.show', $tag) }}"
                        wire:navigate
                        @if ($isCurrent) aria-current="page" @endif
                        @class([
                            'flex h-11 items-center gap-2 rounded-full border px-4 text-sm focus-visible:outline-2 focus-visible:outline-primary-700',
                            'border-primary-700 bg-primary-700 text-white' => $isCurrent,
                            'border-line-200 bg-white text-ink-900 hover:border-primary-300 hover:text-primary-700' => ! $isCurrent,
                        ])
                    >
                        <x-media::picture :media="$tag->getIcon()" slot-name="category-chip" alt="" class="size-6 object-contain" />
                        {{ $tag->getNameFa() }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
