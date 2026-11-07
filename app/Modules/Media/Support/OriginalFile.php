<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use App\Modules\Media\Models\Media;
use Closure;
use Illuminate\Support\Facades\Storage;

/**
 * Works on a local copy of an image's original, whichever disk it is stored on, and writes changes back.
 */
final class OriginalFile
{
    /**
     * @template TResult
     *
     * @param  Closure(string): TResult  $work  receives the path of a local copy; changes to it are saved
     * @return TResult
     */
    public static function edit(Media $media, Closure $work): mixed
    {
        $disk = Storage::disk((string) $media->getAttribute(Media::COLUMN_DISK));
        $path = $media->getPathRelativeToRoot();
        $local = tempnam(sys_get_temp_dir(), 'media-').'.'.pathinfo($media->getFileName(), PATHINFO_EXTENSION);

        file_put_contents($local, (string) $disk->get($path));

        try {
            $result = $work($local);
            $disk->put($path, (string) file_get_contents($local));
            $media->setAttribute('size', (int) filesize($local));

            return $result;
        } finally {
            @unlink($local);
        }
    }
}
