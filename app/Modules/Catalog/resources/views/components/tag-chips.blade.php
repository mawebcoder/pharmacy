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
                <li wire:key="tag-chip-{{ $tag->getId() }}" class="shrink-0">
                    <x-catalog::tag-chip :tag="$tag" :current="$current?->is($tag)" />
                </li>
            @endforeach
        </ul>
    </nav>
@endif
