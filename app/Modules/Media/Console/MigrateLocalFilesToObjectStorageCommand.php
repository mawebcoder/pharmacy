<?php

declare(strict_types=1);

namespace App\Modules\Media\Console;

use App\Modules\Media\Models\Media;
use App\Modules\Media\Support\MediaPathGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Moves images to their configured disks (owner, 2026-11-28), run by hand on the server (or from «فرمان‌ها»), never
 * from the deploy scripts:
 *  - the original to FILESYSTEM_DISK (e.g. the public S3 bucket behind the CDN);
 *  - the generated sizes the storefront shows, the whole "{uuid}/{content hash}/" folder, to the same disk, public and
 *    cached for a year (the folder name changes whenever the image does).
 * Each file is streamed from the disk its record names; a file already on the target disk is not copied again, so an
 * interrupted run can simply be run again. Once every file of a step is there, the record points at the new disk.
 * Old copies are not deleted. A file that cannot be moved is logged with its record and the run goes on.
 */
final class MigrateLocalFilesToObjectStorageCommand extends Command
{
    protected $signature = 'media:migrate-to-s3
        {--model=all : all, or the owners of the images: products, brands, categories, tags, banners…}
        {--chunk=100 : Media records per chunk}
        {--dry-run : List what would be moved without moving it}';

    protected $description = 'Move image originals and generated sizes to FILESYSTEM_DISK, one line per file';

    private int $movedOriginals = 0;

    private int $movedConversions = 0;

    private int $skippedFiles = 0;

    /** @var list<array{media: int, entity: string, file: string, error: string}> */
    private array $failed = [];

    public function handle(): int
    {
        $originalsDisk = (string) config('media-library.disk_name');
        $conversionsDisk = (string) config('media-library.conversions_disk_name');
        $model = (string) $this->option('model');
        $owners = self::owners();

        if ($model !== 'all' && ! isset($owners[$model])) {
            $this->components->error('--model must be all, '.implode(', ', array_keys($owners)).'.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');

        Media::query()
            ->whereIn('model_type', $model === 'all' ? array_values($owners) : [$owners[$model]])
            ->where(fn ($query) => $query
                ->where(Media::COLUMN_DISK, '!=', $originalsDisk)
                ->orWhereNull(Media::COLUMN_CONVERSIONS_DISK)
                ->orWhere(Media::COLUMN_CONVERSIONS_DISK, '!=', $conversionsDisk))
            ->chunkById(max(1, (int) $this->option('chunk')), function (Collection $medias) use ($originalsDisk, $conversionsDisk, $dryRun): void {
                /** @var Media $media */
                foreach ($medias as $media) {
                    // Read before the original moves: an image without its own conversions disk uses the original's.
                    $fromConversions = $media->conversionsDisk();

                    if (! $dryRun) {
                        $this->line(self::entity($media).' (media #'.$media->getId().')');
                    }

                    if ($media->getAttribute(Media::COLUMN_DISK) !== $originalsDisk) {
                        $this->moveOriginal($media, $originalsDisk, $dryRun);
                    }

                    if ($fromConversions !== $conversionsDisk || $media->getAttribute(Media::COLUMN_CONVERSIONS_DISK) !== $conversionsDisk) {
                        $this->moveConversions($media, $fromConversions, $conversionsDisk, $dryRun);
                    }
                }
            });

        $verb = $dryRun ? 'Would move' : 'Moved';
        $this->components->info("{$verb} {$this->movedOriginals} original(s) to the {$originalsDisk} disk.");
        $this->components->info("{$verb} the generated sizes of {$this->movedConversions} image(s) to the {$conversionsDisk} disk.");

        if ($this->skippedFiles > 0) {
            $this->components->info("{$this->skippedFiles} file(s) were already there and were not copied again.");
        }

        if ($this->failed !== []) {
            $this->components->warn(count($this->failed).' file(s) could not be moved (also in the log):');
            $this->table(['Media', 'Entity', 'File', 'Error'], $this->failed);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * The kinds of records that own images, by name: "products" => the Product class, read from the media table.
     *
     * @return array<string, string>
     */
    public static function owners(): array
    {
        return Media::query()->distinct()->orderBy('model_type')->pluck('model_type')
            ->mapWithKeys(fn (string $type): array => [Str::plural(Str::kebab(class_basename($type))) => $type])
            ->all();
    }

    private function moveOriginal(Media $media, string $target, bool $dryRun): void
    {
        $source = (string) $media->getAttribute(Media::COLUMN_DISK);
        $path = $media->getPathRelativeToRoot();

        if ($dryRun) {
            $this->line(self::entity($media).": original {$source}:{$path} → {$target}");
            $this->movedOriginals++;

            return;
        }

        try {
            $this->copy($source, $target, $path, ['mimetype' => $media->getMimeType()]);
        } catch (Throwable $exception) {
            $this->recordFailure($media, "{$source}:{$path}", $target, $exception);

            return;
        }

        $media->setAttribute(Media::COLUMN_DISK, $target)->save();
        $this->movedOriginals++;
    }

    /**
     * Copies every generated file of the image; the record moves only when all of them are copied.
     */
    private function moveConversions(Media $media, string $source, string $target, bool $dryRun): void
    {
        $folder = app(MediaPathGenerator::class)->getPathForConversions($media);
        $files = $source === $target ? [] : Storage::disk($source)->allFiles($folder);

        if ($dryRun) {
            $this->line(self::entity($media).': '.count($files)." generated file(s) {$source}:{$folder} → {$target}");
            $this->movedConversions++;

            return;
        }

        foreach ($files as $file) {
            try {
                $this->copy($source, $target, $file, [
                    'visibility' => 'public',
                    'mimetype' => Storage::disk($source)->mimeType($file) ?: null,
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);
            } catch (Throwable $exception) {
                $this->recordFailure($media, "{$source}:{$file}", $target, $exception);

                return;
            }
        }

        $media->setAttribute(Media::COLUMN_CONVERSIONS_DISK, $target)->save();
        $this->movedConversions++;
    }

    /**
     * Copies one file, unless the target disk already has it.
     *
     * @param  array<string, string|null>  $options
     */
    private function copy(string $source, string $target, string $path, array $options): void
    {
        if (Storage::disk($target)->exists($path)) {
            $this->skippedFiles++;
            $this->line("  <comment>exists</comment>  {$target}:{$path}");

            return;
        }

        $stream = Storage::disk($source)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The file is missing.');
        }

        try {
            if (Storage::disk($target)->writeStream($path, $stream, array_filter($options)) === false) {
                throw new RuntimeException("The {$target} disk refused the file.");
            }
        } finally {
            fclose($stream);
        }

        $this->line("  <info>copied</info>  {$source}:{$path} → {$target}");
    }

    private function recordFailure(Media $media, string $file, string $target, Throwable $exception): void
    {
        $this->line("  <error>failed</error>  {$file} → {$target}: {$exception->getMessage()}");
        $this->failed[] = ['media' => $media->getId(), 'entity' => self::entity($media), 'file' => $file, 'error' => $exception->getMessage()];
        Log::warning('An image file could not be moved', [
            'media_id' => $media->getId(),
            'entity' => self::entity($media),
            'file' => $file,
            'target_disk' => $target,
            'error' => $exception->getMessage(),
        ]);
    }

    private static function entity(Media $media): string
    {
        return class_basename((string) $media->getAttribute('model_type')).' #'.$media->getAttribute('model_id');
    }
}
