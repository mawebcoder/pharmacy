<?php

declare(strict_types=1);

namespace App\Modules\Media\Console;

use App\Modules\Media\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Moves image originals to the originals disk (FILESYSTEM_DISK, e.g. S3; owner, 2026-11-28): each file is streamed
 * from the disk its record names, then the record points at the new disk. Generated sizes stay where they are, and
 * the old copies are not deleted. A file that cannot be moved is logged with its record and the run goes on. Run by
 * hand on the server (or from «فرمان‌ها» in the admin), never from the deploy scripts.
 */
final class MigrateLocalFilesToObjectStorageCommand extends Command
{
    protected $signature = 'media:migrate-to-s3
        {--model=all : all, or the owners of the images: products, brands, categories, tags, banners…}
        {--chunk=100 : Media records per chunk}
        {--dry-run : List what would be moved without moving it}';

    protected $description = 'Move image originals to the originals disk (FILESYSTEM_DISK)';

    public function handle(): int
    {
        $target = (string) config('media-library.disk_name');
        $model = (string) $this->option('model');
        $owners = self::owners();

        if ($model !== 'all' && ! isset($owners[$model])) {
            $this->components->error('--model must be all, '.implode(', ', array_keys($owners)).'.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        /** @var list<array{media: int, entity: string, file: string, error: string}> $failed */
        $failed = [];

        Media::query()
            ->whereIn('model_type', $model === 'all' ? array_values($owners) : [$owners[$model]])
            ->where(Media::COLUMN_DISK, '!=', $target)
            ->chunkById(max(1, (int) $this->option('chunk')), function (Collection $medias) use ($target, $dryRun, &$moved, &$failed): void {
                /** @var Media $media */
                foreach ($medias as $media) {
                    $source = (string) $media->getAttribute(Media::COLUMN_DISK);
                    $path = $media->getPathRelativeToRoot();
                    $entity = class_basename((string) $media->getAttribute('model_type')).' #'.$media->getAttribute('model_id');

                    if ($dryRun) {
                        $this->line("{$entity}: {$source}:{$path} → {$target}");
                        $moved++;

                        continue;
                    }

                    try {
                        $this->copy($source, $target, $path, $media->getMimeType());
                        $media->setAttribute(Media::COLUMN_DISK, $target)->save();
                        $moved++;
                    } catch (Throwable $exception) {
                        $failed[] = ['media' => $media->getId(), 'entity' => $entity, 'file' => "{$source}:{$path}", 'error' => $exception->getMessage()];
                        Log::warning('An image original could not be moved', [
                            'media_id' => $media->getId(),
                            'entity' => $entity,
                            'file' => "{$source}:{$path}",
                            'target_disk' => $target,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->components->info(($dryRun ? 'Would move' : 'Moved')." {$moved} original(s) to the {$target} disk.");

        if ($failed !== []) {
            $this->components->warn(count($failed).' original(s) could not be moved (also in the log):');
            $this->table(['Media', 'Entity', 'File', 'Error'], $failed);

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

    private function copy(string $source, string $target, string $path, ?string $mimeType): void
    {
        $stream = Storage::disk($source)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The original is missing.');
        }

        try {
            if (Storage::disk($target)->writeStream($path, $stream, array_filter(['mimetype' => $mimeType])) === false) {
                throw new RuntimeException("The {$target} disk refused the file.");
            }
        } finally {
            fclose($stream);
        }
    }
}
