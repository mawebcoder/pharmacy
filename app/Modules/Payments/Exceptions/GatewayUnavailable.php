<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * The gateway could not give a payment link (down, refused the request, or not allowed here). Internal: callers turn
 * it into a message for the customer.
 */
final class GatewayUnavailable extends RuntimeException
{
    public static function fakeNotAllowed(): self
    {
        return new self('The fake payment gateway is turned off (payments.fake_enabled).');
    }

    /**
     * The gateway could not be asked or refused to open a payment; the reason is safe to show admins.
     */
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
