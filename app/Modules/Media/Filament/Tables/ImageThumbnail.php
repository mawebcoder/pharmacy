<?php

declare(strict_types=1);

namespace App\Modules\Media\Filament\Tables;

use App\Modules\Media\Support\ImagePreset;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;

/**
 * The round image at the start of an admin table row (products, brands, categories, tags): the smallest WebP size
 * of the collection's preset, fitted inside the circle on white so logos and icons are not cropped. A grey photo icon
 * stands in when there is no image yet.
 */
final class ImageThumbnail
{
    public const string PLACEHOLDER = 'data:image/svg+xml,'
        .'%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%239ca3af%22 stroke-width=%221.5%22%3E'
        .'%3Crect x=%223.5%22 y=%225%22 width=%2217%22 height=%2214%22 rx=%222%22/%3E%3Ccircle cx=%229%22 cy=%2210%22 r=%221.5%22/%3E'
        .'%3Cpath d=%22m20.5 16-5-5-8 8%22/%3E%3C/svg%3E';

    public static function make(string $collection, string $preset): SpatieMediaLibraryImageColumn
    {
        $settings = ImagePreset::named($preset);
        $smallestWidth = $settings->widths === [] ? 80 : min($settings->widths);

        return SpatieMediaLibraryImageColumn::make($collection)
            ->label('تصویر')
            ->collection($collection)
            ->conversion($settings->conversionName($smallestWidth, 'webp'))
            ->limit(1)
            ->circular()
            ->defaultImageUrl(self::PLACEHOLDER)
            ->imageSize(40)
            // Inline: the admin has no custom theme, so utility classes the panel does not already use are not built.
            ->extraImgAttributes(['style' => 'object-fit: contain; padding: 2px; background: #fff; box-shadow: 0 0 0 1px rgb(3 7 18 / 0.1)']);
    }
}
