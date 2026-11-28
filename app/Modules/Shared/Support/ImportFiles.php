<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use Closure;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Spreadsheets the admin uploads to be read once (supplier prices, search synonyms). They stay on the server's own
 * disk (IMPORTS_DISK, "local" by default; owner, 2026-11-28: only images go to S3), named in config, not in code.
 * A reader that needs a real file gets a local copy, so another driver would work too.
 */
final class ImportFiles
{
    public static function disk(): string
    {
        return (string) config('filesystems.imports_disk');
    }

    /**
     * @template TResult
     *
     * @param  Closure(string): TResult  $work  receives the path of a local copy, removed afterwards
     * @return TResult
     */
    public static function withLocalCopy(string $path, Closure $work): mixed
    {
        $source = Storage::disk(self::disk())->readStream($path);

        if (! is_resource($source)) {
            throw new RuntimeException("Cannot read {$path} on the ".self::disk().' disk.');
        }

        // tempnam() reserves a unique name; the copy keeps the file's extension, which the readers go by.
        $reserved = (string) tempnam(sys_get_temp_dir(), 'stored-');
        $local = $reserved.'.'.pathinfo($path, PATHINFO_EXTENSION);
        try {
            if (file_put_contents($local, $source) === false) {
                throw new RuntimeException("Cannot copy {$path} to a local file.");
            }

            return $work($local);
        } finally {
            fclose($source);
            @unlink($local);
            @unlink($reserved);
        }
    }

    public static function delete(string $path): void
    {
        Storage::disk(self::disk())->delete($path);
    }
}
