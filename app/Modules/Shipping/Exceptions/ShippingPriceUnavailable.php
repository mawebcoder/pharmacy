<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A shipping service could not price an order (unreachable, refused, or an answer we cannot read). Checkout then
 * falls back to the flat rate (roadmap S34).
 */
final class ShippingPriceUnavailable extends RuntimeException
{
    public static function from(string $service, Throwable $previous): self
    {
        return new self("{$service} could not price the order: {$previous->getMessage()}", previous: $previous);
    }

    public static function unreadable(string $service): self
    {
        return new self("{$service} answered the price request with a body we cannot read.");
    }
}
