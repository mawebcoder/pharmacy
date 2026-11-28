<?php

declare(strict_types=1);

namespace App\Modules\Media\Filament\Forms;

use App\Modules\Media\Rules\ImageMatchesPreset;
use App\Modules\Media\Support\ImagePreset;
use App\Modules\Shared\Support\DiskVisibility;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

/**
 * An admin upload field for one image collection, validated by its preset (roadmap D14.2, D13.10).
 * The Filament media plugin stores the file when the form is saved; processing then runs on the "media" queue.
 * On create and edit the preview is the uploaded original itself, so the admin sees exactly what was uploaded; on a view
 * page it is a small generated WebP, like the tables' thumbnails (owner, 2026-11-28). Either is at its plain address
 * (AWS_URL, the CDN) when the images disk is public, as the s3 bucket is, else a signed link (DiskVisibility).
 */
final class ImageUpload
{
    public static function make(string $collection, string $preset): SpatieMediaLibraryFileUpload
    {
        $settings = ImagePreset::named($preset);
        // WebP: the upload widget (FilePond) cannot draw AVIF previews.
        $thumbnail = $settings->conversionName($settings->widths[(int) floor((count($settings->widths) - 1) / 2)], 'webp');

        return SpatieMediaLibraryFileUpload::make($collection)
            ->collection($collection)
            ->visibility(DiskVisibility::of((string) config('media-library.disk_name')))
            ->conversion(fn (string $operation): ?string => $operation === 'view' ? $thumbnail : null)
            // image() turns on the preview; the preset's own types then replace its "image/*".
            ->image()
            ->acceptedFileTypes($settings->mimes)
            ->maxSize($settings->maxKilobytes)
            ->rules([new ImageMatchesPreset($settings)])
            ->imagePreviewHeight('120')
            // Crop, rotate and flip before saving. A preset with a fixed ratio offers only that crop, so a photo can be
            // cut to fit instead of being refused; SVGs stay vector (the editor would turn them into pixels).
            ->imageEditor()
            ->imageEditorAspectRatioOptions(self::aspectRatios($settings))
            ->editableSvgs(false)
            ->helperText(self::helperText($settings));
    }

    /**
     * @return list<string|null>
     */
    private static function aspectRatios(ImagePreset $preset): array
    {
        return $preset->ratio === null ? [null, '1:1'] : ["{$preset->minWidth}:{$preset->minHeight}"];
    }

    private static function helperText(ImagePreset $preset): string
    {
        $size = PersianDigits::toPersian($preset->minWidth).'×'.PersianDigits::toPersian($preset->minHeight);
        $longest = PersianDigits::toPersian(max($preset->minWidth, $preset->minHeight));
        $text = match (true) {
            $preset->padToSquare && $preset->trimBeforePadding => "ضلع بلندتر دست‌کم {$longest} پیکسل، با هر نسبتی؛ حاشیه حذف و تصویر روی زمینه سفید مربع می‌شود.",
            $preset->padToSquare => "ضلع بلندتر دست‌کم {$longest} پیکسل، با هر نسبتی؛ تصویر بدون برش و کشیدگی روی زمینه سفید مربع می‌شود.",
            default => "دست‌کم {$size} پیکسل با همین نسبت ابعاد.",
        };

        return $text.' حداکثر '.PersianDigits::toPersian($preset->maxKilobytes).' کیلوبایت'
            .($preset->acceptsSvg() ? '؛ SVG ترجیح دارد.' : '.');
    }
}
