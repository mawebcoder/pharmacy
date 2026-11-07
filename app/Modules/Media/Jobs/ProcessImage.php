<?php

declare(strict_types=1);

namespace App\Modules\Media\Jobs;

use App\Modules\Media\Enums\MediaStatus;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Support\ImageNormalizer;
use App\Modules\Media\Support\ImagePlaceholder;
use App\Modules\Media\Support\ImagePreset;
use App\Modules\Media\Support\OriginalFile;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Throwable;

/**
 * The image pipeline on the "media" queue (roadmap D9, D13.10), used by medialibrary in place of its own
 * conversions job: normalize the original, store its size, ThumbHash and dominant colour, generate the preset's
 * sizes, then mark the image ready (or failed with the error).
 */
final class ProcessImage extends PerformConversionsJob
{
    /**
     * AVIF encoding of large banners can take a while.
     */
    public int $timeout = 300;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function handle(FileManipulator $fileManipulator): bool
    {
        $media = $this->media;

        if (! $media instanceof Media) {
            return parent::handle($fileManipulator);
        }

        try {
            $preset = $media->getPreset();

            if ($preset instanceof ImagePreset && ! $this->onlyMissing) {
                OriginalFile::edit($media, function (string $path) use ($media, $preset): void {
                    $size = app(ImageNormalizer::class)->normalize($path, $preset);
                    $placeholder = app(ImagePlaceholder::class)->for($path);

                    $media->setDimensions($size['width'], $size['height'])
                        ->setThumbhash($placeholder['thumbhash'])
                        ->setDominantColor($placeholder['color']);
                });
                $media->save();
            }

            parent::handle($fileManipulator);

            $media->refresh()->setStatus(MediaStatus::Ready)->save();
        } catch (Throwable $exception) {
            $media->setStatus(MediaStatus::Failed, $exception->getMessage())->save();

            throw $exception;
        }

        return true;
    }
}
