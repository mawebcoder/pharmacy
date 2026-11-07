<?php

declare(strict_types=1);

namespace App\Modules\Media\Filament\Forms;

use App\Modules\Media\Rules\ImageMatchesPreset;
use App\Modules\Media\Support\ImagePreset;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

/**
 * An admin upload field for one image collection, validated by its preset (roadmap D14.2, D13.10).
 * The Filament media plugin stores the file when the form is saved; processing then runs on the "media" queue.
 * The original stays private, so the preview uses a generated WebP size once it exists, else a temporary URL.
 */
final class ImageUpload
{
    public static function make(string $collection, string $preset): SpatieMediaLibraryFileUpload
    {
        $settings = ImagePreset::named($preset);
        $previewWidth = $settings->widths[(int) floor((count($settings->widths) - 1) / 2)];

        return SpatieMediaLibraryFileUpload::make($collection)
            ->collection($collection)
            ->visibility('private')
            // WebP: the upload widget (FilePond) cannot draw AVIF previews.
            ->conversion($settings->conversionName($previewWidth, 'webp'))
            // image() turns on the preview; the preset's own types then replace its "image/*".
            ->image()
            ->acceptedFileTypes($settings->mimes)
            ->maxSize($settings->maxKilobytes)
            ->rules([new ImageMatchesPreset($settings)])
            ->imagePreviewHeight('120')
            ->helperText(self::helperText($settings));
    }

    private static function helperText(ImagePreset $preset): string
    {
        $size = PersianDigits::toPersian($preset->minWidth).'×'.PersianDigits::toPersian($preset->minHeight);
        $text = $preset->padToSquare
            ? "دست‌کم {$size} پیکسل؛ حاشیه حذف و تصویر روی زمینه سفید مربع می‌شود."
            : "دست‌کم {$size} پیکسل با همین نسبت ابعاد.";

        return $text.' حداکثر '.PersianDigits::toPersian($preset->maxKilobytes).' کیلوبایت'
            .($preset->acceptsSvg() ? '؛ SVG ترجیح دارد.' : '.');
    }
}
