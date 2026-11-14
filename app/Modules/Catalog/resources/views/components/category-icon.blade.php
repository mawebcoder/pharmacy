{{-- A category's icon in a round white well (owner, 2026-11-14), or a neutral glyph without one: <x-catalog::category-icon :icon="$category->getIcon()" /> --}}
@props(['icon' => null])

<span {{ $attributes->class('grid size-11 shrink-0 place-items-center overflow-hidden rounded-full border border-line-200 bg-media p-1.5') }}>
    @if ($icon)
        <x-media::picture :media="$icon" slot-name="category-icon" alt="" class="size-full object-contain" />
    @else
        <x-ts-icon icon="squares-2x2" class="size-5 text-ink-500" />
    @endif
</span>
