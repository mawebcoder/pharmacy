{{--
    Responsive image (roadmap D14.5): <x-media::picture :media="$brand->getLogo()" slot-name="brand-logo" alt="اوسرین" />
    - AVIF/WebP <source>s with srcset and the slot's `sizes`, then an <img> fallback with width/height (no layout shift)
    - lazy by default; :priority="true" for the largest image above the fold (eager, fetchpriority="high")
    - dominant colour (or ThumbHash for banners) behind the image until it loads
    - SVG is served as-is; an image still processing shows only its placeholder
    Renders nothing when $media is null, so callers decide the fallback.
--}}
{{-- "slot-name", not "slot": $slot is Blade's own variable for a component's content. --}}
@props([
    'media' => null,
    'slotName',
    'alt' => '',
    'priority' => false,
])

@use('App\Modules\Media\Support\ImageSlot')
@use('App\Modules\Media\Support\Picture')

@if ($media)
    @php
        $imageSlot = ImageSlot::named($slotName);
        $altText = $alt !== '' ? $alt : ($media->getAltFa() ?? '');
        $loading = $priority ? ['loading' => 'eager', 'fetchpriority' => 'high'] : ['loading' => 'lazy'];
    @endphp

    @if ($media->isSvg() && $media->isReady())
        <img src="{{ $media->svgUrl() }}" alt="{{ $altText }}" decoding="async" {{ $attributes->merge($loading) }}>
    @elseif (! $media->isReady())
        <span role="img" aria-label="{{ $altText }}" style="{{ Picture::placeholderStyle($media, $imageSlot) }}" {{ $attributes->class('block') }}></span>
    @else
        @php($picture = Picture::for($media, $imageSlot))
        <picture>
            @foreach ($picture->sources as $source)
                <source type="{{ $source['type'] }}" srcset="{{ $source['srcset'] }}" sizes="{{ $picture->sizes }}">
            @endforeach
            <img
                src="{{ $picture->src }}"
                srcset="{{ $picture->srcset }}"
                sizes="{{ $picture->sizes }}"
                @if ($picture->width) width="{{ $picture->width }}" @endif
                @if ($picture->height) height="{{ $picture->height }}" @endif
                alt="{{ $altText }}"
                decoding="async"
                style="{{ $picture->placeholderStyle }}"
                {{ $attributes->merge($loading) }}
            >
        </picture>
    @endif
@endif
