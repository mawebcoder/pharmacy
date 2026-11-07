<?php

declare(strict_types=1);

namespace App\Modules\Media\Concerns;

use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Media\Support\ImagePreset;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * Registers one collection per entry of imagePresets() (single-image unless listed in multipleImageCollections()), and for raster images the conversions of
 * that preset: every width × format, queued on the "media" queue (roadmap D14.6). SVG gets no conversions; it is
 * sanitized and published as-is.
 *
 * @phpstan-require-implements HasImagePresets
 */
trait InteractsWithImagePresets
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        foreach ($this->imagePresets() as $collection => $presetName) {
            $preset = ImagePreset::named($presetName);

            $mediaCollection = $this->addMediaCollection($collection)->acceptsMimeTypes($preset->mimes);

            if (! in_array($collection, $this->multipleImageCollections(), true)) {
                $mediaCollection->singleFile();
            }
        }
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        if ($media instanceof SpatieMedia && $media->mime_type === ImagePreset::SVG) {
            return;
        }

        foreach ($this->imagePresets() as $collection => $presetName) {
            if ($media instanceof SpatieMedia && $media->collection_name !== $collection) {
                continue;
            }

            $preset = ImagePreset::named($presetName);

            foreach ($preset->widths as $width) {
                foreach ($preset->formats as $format => $quality) {
                    $this->addMediaConversion($preset->conversionName($width, $format))
                        ->performOnCollections($collection)
                        ->queued()
                        ->format($format)
                        // Shrink only: a copy is never larger than the original (no blurry upscaling).
                        ->fit(Fit::Max, $width, $width * 10)
                        ->quality($quality)
                        ->optimize();
                }
            }
        }
    }

    /**
     * Collections that hold several images in order (e.g. a product gallery); all others hold one.
     *
     * @return list<string>
     */
    public function multipleImageCollections(): array
    {
        return [];
    }

    /**
     * Every image in a collection, in order.
     *
     * @return list<StoredImage>
     */
    public function images(string $collection): array
    {
        return array_values($this->getMedia($collection)->filter(fn ($media): bool => $media instanceof StoredImage)->all());
    }

    /**
     * The image in a collection, or null when there is none.
     */
    public function image(string $collection): ?StoredImage
    {
        $media = $this->getFirstMedia($collection);

        return $media instanceof StoredImage ? $media : null;
    }
}
