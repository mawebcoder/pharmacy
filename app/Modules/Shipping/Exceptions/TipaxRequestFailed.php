<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Exceptions;

use RuntimeException;

/**
 * Tipax could not be reached, refused the credentials or answered with something unexpected. The message is safe
 * to show to admins (no secrets).
 */
final class TipaxRequestFailed extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Tipax is not configured: set TIPAX_USERNAME, TIPAX_PASSWORD and TIPAX_API_KEY in .env.');
    }

    public static function unreachable(string $operation): self
    {
        return new self("Tipax did not answer ({$operation}).");
    }

    public static function failed(string $operation, int $status): self
    {
        return new self("Tipax refused {$operation} (HTTP {$status}).");
    }

    /**
     * HTTP 200 with `isSuccess: false` (D6.2), e.g. `CityNotFound` or `ServiceNotAvailable`.
     */
    public static function refused(string $operation, string $statusCode): self
    {
        // A Persian message from Tipax is shown as it is; a bare status code gets a short English frame for admins.
        return new self(preg_match('/\p{Arabic}/u', $statusCode) === 1 ? $statusCode : "Tipax refused {$operation} ({$statusCode}).");
    }

    public static function unexpected(string $operation): self
    {
        return new self("Tipax answered {$operation} with an unexpected body.");
    }
}
