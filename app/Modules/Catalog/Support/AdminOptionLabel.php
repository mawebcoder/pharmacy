<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Media\Filament\Tables\ImageThumbnail;

/**
 * The HTML of one option in an admin select (S13b): a round image, the name and an optional second line (for SKUs:
 * the SKU title, code and id). Styled inline because the admin has no custom theme. Text is escaped.
 */
final class AdminOptionLabel
{
    public static function html(?StoredImage $image, int $width, string $title, ?string $subtitle = null): string
    {
        $src = match (true) {
            ! $image instanceof StoredImage => null,
            $image->isSvg() => $image->svgUrl(),
            default => $image->url($width, 'webp'),
        };

        return '<span style="display:flex;align-items:center;gap:.5rem">'
            .'<img src="'.e($src ?? ImageThumbnail::PLACEHOLDER).'" alt="" loading="lazy" style="width:2rem;height:2rem;flex:none;border-radius:9999px;object-fit:contain;padding:2px;background:#fff;box-shadow:0 0 0 1px rgb(3 7 18 / 0.1)">'
            .'<span style="min-width:0"><span style="display:block">'.e($title).'</span>'
            .($subtitle === null ? '' : '<span style="display:block;font-size:.75rem;opacity:.7" dir="auto">'.e($subtitle).'</span>')
            .'</span></span>';
    }
}
