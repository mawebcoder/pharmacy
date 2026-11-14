<?php

declare(strict_types=1);

namespace App\Modules\Cart\Exceptions;

use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Support\PersianDigits;

/**
 * A cart change that cannot be made; the message is shown to the shopper.
 */
final class CartRefused extends BaseException
{
    public static function cannotBuy(string $reason): self
    {
        return new self($reason, 'cart_item_not_purchasable');
    }

    public static function limitReached(int $max): self
    {
        return new self('از این کالا حداکثر '.PersianDigits::toPersian($max).' عدد در هر سفارش می‌توانید بخرید.', 'cart_item_limit_reached');
    }

    public static function notFound(): self
    {
        return new self('این کالا در سبد خرید شما نیست.', 'cart_item_not_found');
    }
}
