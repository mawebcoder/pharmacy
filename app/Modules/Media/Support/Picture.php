<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use App\Modules\Media\Contracts\StoredImage;

/**
 * What <x-media::picture> renders for an image in a slot (roadmap D14.5): one <source> per modern format with
 * its srcset, an <img> fallback, intrinsic width/height (no layout shift) and the placeholder background.
 */
final readonly class Picture
{
    /**
     * @param  list<array{type: string, srcset: string}>  $sources
     */
    public function __construct(
        public array $sources,
        public string $src,
        public string $srcset,
        public string $sizes,
        public ?int $width,
        public ?int $height,
        public string $placeholderStyle,
    ) {}

    public static function for(StoredImage $media, ImageSlot $slot): self
    {
        $preset = $slot->preset;
        $widths = self::availableWidths($media, $slot);
        $fallback = $preset->fallbackFormat();
        $srcset = fn (string $format): string => implode(', ', array_map(
            fn (int $width): string => $media->url($width, $format).' '.$width.'w',
            $widths,
        ));

        $sources = [];

        foreach (array_keys($preset->formats) as $format) {
            if ($format !== $fallback) {
                $sources[] = ['type' => self::mimeOf($format), 'srcset' => $srcset($format)];
            }
        }

        $largest = $widths === [] ? 0 : max($widths);

        return new self(
            sources: $sources,
            src: (string) $media->url($largest, $fallback),
            srcset: $srcset($fallback),
            sizes: $slot->sizes,
            width: $largest === 0 ? null : $largest,
            height: $largest === 0 ? null : $media->heightFor($largest),
            placeholderStyle: self::placeholderStyle($media, $slot),
        );
    }

    /**
     * The slot's widths that were generated for real: copies are never enlarged, so widths above the original's
     * width are left out (a 1200 px photo offers up to 1200w, not a blurry 1600w). At least the smallest stays.
     *
     * @return list<int>
     */
    public static function availableWidths(StoredImage $media, ImageSlot $slot): array
    {
        $widths = array_values(array_intersect($slot->widths, $slot->preset->widths));
        $originalWidth = $media->getWidth();

        if ($originalWidth === null || $widths === []) {
            return $widths;
        }

        $fitting = array_values(array_filter($widths, fn (int $width): bool => $width <= $originalWidth));

        return $fitting === [] ? [min($widths)] : $fitting;
    }

    /**
     * Background shown until the image loads. None for transparent artwork (logos, icons: no JPEG output), whose
     * transparent areas would otherwise show the colour after loading.
     */
    public static function placeholderStyle(StoredImage $media, ImageSlot $slot): string
    {
        if (! $slot->preset->isOpaque()) {
            return '';
        }

        $thumbhash = $slot->thumbhashPlaceholder ? $media->thumbhashDataUrl() : null;

        if ($thumbhash !== null) {
            return "background-image:url({$thumbhash});background-size:cover";
        }

        $color = $media->getDominantColor();

        return $color === null ? '' : "background-color:{$color}";
    }

    private static function mimeOf(string $format): string
    {
        return match ($format) {
            'jpg' => 'image/jpeg',
            default => "image/{$format}",
        };
    }
}
