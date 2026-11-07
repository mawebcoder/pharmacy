<?php

declare(strict_types=1);

namespace App\Modules\Media\Contracts;

use Spatie\MediaLibrary\HasMedia;

/**
 * A model with image collections, each processed by a preset from config/media-presets.php.
 * Use the InteractsWithImagePresets trait to register the collections and conversions.
 */
interface HasImagePresets extends HasMedia
{
    /**
     * @return array<string, string> collection name => preset name, e.g. ['logo' => 'brand_logo']
     */
    public function imagePresets(): array;
}
