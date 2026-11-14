<?php

declare(strict_types=1);

namespace App\Modules\Locations\Exceptions;

use RuntimeException;

/**
 * A Tipax city list that cannot be imported; nothing is saved.
 */
final class InvalidTipaxCities extends RuntimeException
{
    public static function missingFile(string $path): self
    {
        return new self("Tipax city file not found: {$path}");
    }

    public static function invalidJson(string $path): self
    {
        return new self("Tipax city file is not a JSON list: {$path}");
    }

    public static function invalidRow(int $index, string $field): self
    {
        return new self("Invalid Tipax city #{$index}: missing or wrong \"{$field}\".");
    }
}
