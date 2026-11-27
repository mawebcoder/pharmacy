{{--
    SEO <head> tags of every storefront page (roadmap S38): title «… | هوتانا», description, canonical address, robots,
    Open Graph and Twitter card for link previews, and the page's JSON-LD. Pages pass `title` (->title()), and optionally
    `description` and `seo` (App\Modules\Seo\Data\SeoMeta) through ->layoutData(). Rules: App\Modules\Seo\Support\SeoDefaults.
--}}
@props(['title' => null, 'description' => null, 'seo' => null])

@use('App\Modules\Seo\Support\SeoDefaults')

@php
    $siteName = (string) config('app.display_name');
    $fullTitle = filled($title) ? $title.' | '.$siteName : $siteName;
    $metaDescription = $seo?->description ?? (filled($description) ? $description : SeoDefaults::DESCRIPTION);
    $canonical = SeoDefaults::canonical(request());
    $noindex = SeoDefaults::isNoindex(request(), $seo);
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large' }}">

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="fa_IR">
<meta property="og:type" content="{{ $seo?->type ?? 'website' }}">
<meta property="og:title" content="{{ filled($title) ? $title : $siteName }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonical }}">
@if ($seo?->image)
    <meta property="og:image" content="{{ $seo->image }}">
@endif
<meta name="twitter:card" content="{{ $seo?->image ? 'summary_large_image' : 'summary' }}">

@foreach ($seo?->structuredData ?? [] as $block)
    <script type="application/ld+json">{!! json_encode($block, $jsonFlags) !!}</script>
@endforeach
