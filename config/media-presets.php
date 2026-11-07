<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Image presets and slots (roadmap D14)
|--------------------------------------------------------------------------
|
| A preset describes one kind of uploaded original (D14.2) and the files generated from it (D14.3, D14.4):
|
| - min_width / min_height: smallest accepted original, in pixels (SVG is exempt)
| - ratio: width ÷ height the original must have (± ratio_tolerance); null = any ratio
| - pad_to_square: trim the border and pad to a white square before resizing (product photos)
| - max_kb, mimes: largest file and accepted types
| - widths: generated widths in pixels; formats: output format => quality
|
| A slot is a place on the page that shows images of one preset: which generated widths it uses and the
| `sizes` attribute for <picture> (D14.3 rendered sizes per device). The placeholder is the dominant colour, or the
| ThumbHash for banners (D14.5).
|
*/

return [
    'ratio_tolerance' => 0.02,

    'presets' => [
        'product' => [
            'min_width' => 1200, 'min_height' => 1200, 'ratio' => null, 'pad_to_square' => true,
            'max_kb' => 5120, 'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'widths' => [80, 160, 240, 320, 400, 480, 640, 800, 1200, 1600],
            'formats' => ['avif' => 50, 'webp' => 75, 'jpg' => 80],
        ],
        'banner_desktop' => [
            'min_width' => 1920, 'min_height' => 600, 'ratio' => 16 / 5,
            'max_kb' => 5120, 'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'widths' => [1280, 1920],
            'formats' => ['avif' => 50, 'webp' => 75, 'jpg' => 80],
        ],
        'banner_tablet' => [
            'min_width' => 1536, 'min_height' => 768, 'ratio' => 2,
            'max_kb' => 5120, 'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'widths' => [1024, 1536],
            'formats' => ['avif' => 50, 'webp' => 75, 'jpg' => 80],
        ],
        'banner_mobile' => [
            'min_width' => 800, 'min_height' => 1000, 'ratio' => 4 / 5,
            'max_kb' => 5120, 'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'widths' => [480, 800],
            'formats' => ['avif' => 50, 'webp' => 75, 'jpg' => 80],
        ],
        // Category page banner (decided 2026-11-07 in S09): a desktop/tablet image and a separate mobile image.
        'category_banner_desktop' => [
            'min_width' => 1920, 'min_height' => 480, 'ratio' => 4,
            'max_kb' => 5120, 'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'widths' => [768, 1280, 1920],
            'formats' => ['avif' => 50, 'webp' => 75, 'jpg' => 80],
        ],
        'category_banner_mobile' => [
            'min_width' => 800, 'min_height' => 600, 'ratio' => 4 / 3,
            'max_kb' => 5120, 'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'widths' => [480, 800],
            'formats' => ['avif' => 50, 'webp' => 75, 'jpg' => 80],
        ],
        // Transparent artwork: SVG is served as-is (sanitized); PNG/WebP become WebP, which keeps transparency.
        'category_icon' => [
            'min_width' => 256, 'min_height' => 256, 'ratio' => 1,
            'max_kb' => 500, 'mimes' => ['image/svg+xml', 'image/png'],
            'widths' => [96, 192],
            'formats' => ['webp' => 90],
        ],
        'brand_logo' => [
            'min_width' => 400, 'min_height' => 200, 'ratio' => 2,
            'max_kb' => 1024, 'mimes' => ['image/svg+xml', 'image/png', 'image/webp'],
            'widths' => [160, 320],
            'formats' => ['webp' => 90],
        ],
    ],

    'slots' => [
        'product-card' => ['preset' => 'product', 'widths' => [160, 320, 480, 640], 'sizes' => '(min-width: 1280px) 240px, (min-width: 768px) 220px, 50vw'],
        'cart-thumb' => ['preset' => 'product', 'widths' => [80, 160, 240], 'sizes' => '(min-width: 768px) 80px, 64px'],
        'pdp-main' => ['preset' => 'product', 'widths' => [400, 800, 1200], 'sizes' => '(min-width: 1280px) 560px, (min-width: 768px) 600px, 100vw'],
        'pdp-zoom' => ['preset' => 'product', 'widths' => [1600], 'sizes' => '1600px'],
        'brand-logo' => ['preset' => 'brand_logo', 'widths' => [160, 320], 'sizes' => '(min-width: 1280px) 160px, (min-width: 768px) 120px, 96px'],
        'category-chip' => ['preset' => 'category_icon', 'widths' => [96], 'sizes' => '24px'],
        'category-icon' => ['preset' => 'category_icon', 'widths' => [96, 192], 'sizes' => '(min-width: 1280px) 64px, (min-width: 768px) 56px, 48px'],
        'category-banner-desktop' => ['preset' => 'category_banner_desktop', 'widths' => [768, 1280, 1920], 'sizes' => '(min-width: 1280px) 1216px, 100vw', 'placeholder' => 'thumbhash'],
        'category-banner-mobile' => ['preset' => 'category_banner_mobile', 'widths' => [480, 800], 'sizes' => '100vw', 'placeholder' => 'thumbhash'],
    ],
];
