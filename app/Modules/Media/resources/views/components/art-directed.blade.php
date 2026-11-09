{{--
    Banner with separate artwork per screen (roadmap D14.3, D14.5): the desktop image, an optional tablet image from
    768px (then the desktop image starts at 1280px), and the mobile image below.
    <x-media::art-directed :desktop="$d" desktop-slot="category-banner-desktop" :mobile="$m" mobile-slot="category-banner-mobile" alt="…" />
    <x-media::art-directed :desktop="$d" desktop-slot="banner-desktop" :tablet="$t" tablet-slot="banner-tablet" :mobile="$m" mobile-slot="banner-mobile" alt="…" />
    Missing images are skipped: with only one image, that image is used everywhere.
--}}
@props([
    'desktop' => null,
    'tablet' => null,
    'mobile' => null,
    'desktopSlot',
    'tabletSlot' => null,
    'mobileSlot',
    'alt' => '',
    'priority' => false,
])

@use('App\Modules\Media\Support\ImageSlot')
@use('App\Modules\Media\Support\Picture')

@php
    $isReady = fn ($media): bool => (bool) $media?->isReady() && ! $media->isSvg();
    $tabletReady = $tabletSlot !== null && $isReady($tablet);

    // Wider artwork first, each with the screen width it starts at; the narrowest ready image is the <img>.
    $tiers = array_values(array_filter([
        $isReady($desktop) ? [$desktop, $desktopSlot, $tabletReady ? '(min-width: 1280px)' : '(min-width: 768px)'] : null,
        $tabletReady ? [$tablet, $tabletSlot, '(min-width: 768px)'] : null,
    ]));
    $fallback = $isReady($mobile) ? [$mobile, $mobileSlot] : (array_pop($tiers) ?? null);
@endphp

@if ($fallback !== null && $tiers !== [])
    @php($narrow = Picture::for($fallback[0], ImageSlot::named($fallback[1])))
    <picture>
        @foreach ($tiers as [$media, $slotName, $query])
            @php($wide = Picture::for($media, ImageSlot::named($slotName)))
            @foreach ($wide->sources as $source)
                <source media="{{ $query }}" type="{{ $source['type'] }}" srcset="{{ $source['srcset'] }}" sizes="{{ $wide->sizes }}" width="{{ $wide->width }}" height="{{ $wide->height }}">
            @endforeach
            <source media="{{ $query }}" srcset="{{ $wide->srcset }}" sizes="{{ $wide->sizes }}" width="{{ $wide->width }}" height="{{ $wide->height }}">
        @endforeach
        @foreach ($narrow->sources as $source)
            <source type="{{ $source['type'] }}" srcset="{{ $source['srcset'] }}" sizes="{{ $narrow->sizes }}">
        @endforeach
        <img
            src="{{ $narrow->src }}"
            srcset="{{ $narrow->srcset }}"
            sizes="{{ $narrow->sizes }}"
            width="{{ $narrow->width }}"
            height="{{ $narrow->height }}"
            alt="{{ $alt }}"
            decoding="async"
            style="{{ $narrow->placeholderStyle }}"
            {{ $attributes->merge($priority ? ['loading' => 'eager', 'fetchpriority' => 'high'] : ['loading' => 'lazy']) }}
        >
    </picture>
@elseif ($fallback !== null)
    <x-media::picture :media="$fallback[0]" :slot-name="$fallback[1]" :alt="$alt" :priority="$priority" {{ $attributes }} />
@elseif ($desktop || $tablet || $mobile)
    {{-- Still processing (or SVG): the picture component shows the placeholder or the file as-is. --}}
    <x-media::picture
        :media="$desktop ?? $tablet ?? $mobile"
        :slot-name="$desktop ? $desktopSlot : ($tablet ? $tabletSlot : $mobileSlot)"
        :alt="$alt"
        :priority="$priority"
        {{ $attributes }}
    />
@endif
