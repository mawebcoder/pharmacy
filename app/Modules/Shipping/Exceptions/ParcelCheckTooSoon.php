<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Exceptions;

use RuntimeException;

/**
 * The customer asked about their parcel again within the hour after a successful answer (owner, 2026-11-19).
 */
final class ParcelCheckTooSoon extends RuntimeException
{
    public function __construct(public readonly int $minutesLeft)
    {
        parent::__construct("Try again in {$minutesLeft} minutes.");
    }
}
