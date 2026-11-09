{{--
    One nutrient / need chip (roadmap B10 chip): icon + name, linking to /t/{slug}. Used by <x-catalog::tag-chips> and
    the home tags slider.
    <x-catalog::tag-chip :tag="$tag" :current="$isCurrent" />
--}}
@props([
    'tag',
    'current' => false,
])

<a
    href="{{ route('tags.show', $tag) }}"
    wire:navigate
    @if ($current) aria-current="page" @endif
    {{ $attributes->class([
        'flex h-11 items-center gap-2 rounded-full border px-4 text-sm whitespace-nowrap focus-visible:outline-2 focus-visible:outline-primary-700',
        'border-primary-700 bg-primary-700 text-white' => $current,
        'border-line-200 bg-white text-ink-900 hover:border-primary-300 hover:text-primary-700' => ! $current,
    ]) }}
>
    <x-media::picture :media="$tag->getIcon()" slot-name="category-chip" alt="" class="size-6 object-contain" />
    {{ $tag->getNameFa() }}
</a>
