<?php

declare(strict_types=1);

namespace App\Modules\Locations\Exceptions;

use RuntimeException;

final class InvalidLocationDataset extends RuntimeException
{
    public static function missingFile(string $path): self
    {
        return new self("Location dataset file not found: {$path}");
    }

    public static function invalidJson(string $path): self
    {
        return new self("Location dataset file is not a JSON list: {$path}");
    }

    public static function invalidRow(string $file, int $index, string $field): self
    {
        return new self("Invalid {$file} row #{$index}: missing or wrong \"{$field}\".");
    }

    public static function unknownParent(string $file, int $index, string $field, int $sourceId): self
    {
        return new self("Invalid {$file} row #{$index}: {$field} {$sourceId} does not exist.");
    }

    public static function downloadFailed(string $url, int $status): self
    {
        return new self("Could not download {$url} (HTTP {$status}).");
    }
}
