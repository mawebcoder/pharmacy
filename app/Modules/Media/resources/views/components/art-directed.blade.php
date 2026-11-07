{{--
    Banner with separate desktop and mobile artwork (roadmap D14.3, D14.5): the desktop image from 768px up
    (tablet uses it too), the mobile image below.
    <x-media::art-directed :desktop="$d" desktop-slot="category-banner-desktop" :mobile="$m" mobile-slot="category-banner-mobile" alt="…" />
    With only one of the two images, that image is used everywhere.
--}}
@props([
    'desktop' => null,
    'mobile' => null,
    'desktopSlot',
    'mobileSlot',
    'alt' => '',
    'priority' => false,
])

@use('App\Modules\Media\Support\ImageSlot')
@use('App\Modules\Media\Support\Picture')

@php
    $desktopReady = $desktop?->isReady() && ! $desktop->isSvg();
    $mobileReady = $mobile?->isReady() && ! $mobile->isSvg();
@endphp

@if ($desktopReady && $mobileReady)
    @php
        $wide = Picture::for($desktop, ImageSlot::named($desktopSlot));
        $narrow = Picture::for($mobile, ImageSlot::named($mobileSlot));
    @endphp
    <picture>
        @foreach ($wide->sources as $source)
            <source media="(min-width: 768px)" type="{{ $source['type'] }}" srcset="{{ $source['srcset'] }}" sizes="{{ $wide->sizes }}">
        @endforeach
        <source media="(min-width: 768px)" srcset="{{ $wide->srcset }}" sizes="{{ $wide->sizes }}" width="{{ $wide->width }}" height="{{ $wide->height }}">
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
@elseif ($desktop || $mobile)
    <x-media::picture
        :media="$desktopReady || ! $mobile ? $desktop : $mobile"
        :slot-name="$desktopReady || ! $mobile ? $desktopSlot : $mobileSlot"
        :alt="$alt"
        :priority="$priority"
        {{ $attributes }}
    />
@endif
