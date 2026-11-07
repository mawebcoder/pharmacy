<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use App\Modules\Media\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Original: "{uuid}/" on the private disk. Generated files: "{uuid}/{content hash}/" on the public disk, so a
 * replaced or reprocessed image never reuses a URL and files can be cached forever (roadmap D14.4).
 */
final class MediaPathGenerator implements PathGenerator
{
    public function getPath(SpatieMedia $media): string
    {
        return $media->uuid.'/';
    }

    public function getPathForConversions(SpatieMedia $media): string
    {
        $hash = $media instanceof Media ? $media->getContentHash() : null;

        return $media->uuid.'/'.($hash ?? 'files').'/';
    }

    public function getPathForResponsiveImages(SpatieMedia $media): string
    {
        return $this->getPathForConversions($media).'responsive/';
    }
}
