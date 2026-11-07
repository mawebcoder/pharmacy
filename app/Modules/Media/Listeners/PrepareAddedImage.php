<?php

declare(strict_types=1);

namespace App\Modules\Media\Listeners;

use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Enums\MediaStatus;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Support\OriginalFile;
use App\Modules\Media\Support\SvgSanitizer;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Runs right after an image is stored, before its sizes are queued: records the preset and a content hash (part of
 * the public paths) and marks it as processing. SVG needs no sizes: it is sanitized, published as-is and ready.
 */
final class PrepareAddedImage
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;
        $owner = $media->model;

        if (! $media instanceof Media || ! $owner instanceof HasImagePresets) {
            return;
        }

        $preset = $owner->imagePresets()[$media->getCollectionName()] ?? null;

        if ($preset === null) {
            return;
        }

        $media->setPresetName($preset)->setStatus(MediaStatus::Processing);

        if ($media->isSvg()) {
            $svg = OriginalFile::edit($media, function (string $path): string {
                $clean = app(SvgSanitizer::class)->sanitize((string) file_get_contents($path));
                file_put_contents($path, $clean);

                return $clean;
            });

            $media->setContentHash(substr(sha1($svg), 0, 12));
            Storage::disk($media->conversionsDisk())->put($media->svgPath(), $svg, ['visibility' => 'public']);
            $media->setStatus(MediaStatus::Ready);
        } else {
            $stream = Storage::disk((string) $media->getAttribute(Media::COLUMN_DISK))->get($media->getPathRelativeToRoot());
            $media->setContentHash(substr(sha1((string) $stream), 0, 12));
        }

        $media->save();
    }
}
