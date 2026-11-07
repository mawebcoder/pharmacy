<?php

declare(strict_types=1);

namespace App\Modules\Media\Filament\Tables;

use App\Modules\Media\Support\ImagePreset;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;

/**
 * The round image at the start of an admin table row (products, brands, categories, tags): the smallest WebP size
 * of the collection's preset, fitted inside the circle on white so logos and icons are not cropped.
 */
final class ImageThumbnail
{
    public static function make(string $collection, string $preset): SpatieMediaLibraryImageColumn
    {
        $settings = ImagePreset::named($preset);
        $smallestWidth = $settings->widths === [] ? 80 : min($settings->widths);

        return SpatieMediaLibraryImageColumn::make($collection)
            ->label('')
            ->collection($collection)
            ->conversion($settings->conversionName($smallestWidth, 'webp'))
            ->limit(1)
            ->circular()
            ->imageSize(40)
            ->extraImgAttributes(['class' => 'bg-white object-contain p-0.5 ring-1 ring-gray-950/10 dark:ring-white/20']);
    }
}
