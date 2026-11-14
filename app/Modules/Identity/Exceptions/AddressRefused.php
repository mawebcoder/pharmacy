<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Support\PersianDigits;
use Symfony\Component\HttpFoundation\Response;

/**
 * An address change the customer may not make (roadmap D3.3); shown to the customer as is.
 */
final class AddressRefused extends BaseException
{
    public static function tooMany(int $limit): self
    {
        return new self('حداکثر '.PersianDigits::toPersian($limit).' آدرس می‌توانید ذخیره کنید؛ یکی را حذف کنید.', 'address_limit_reached');
    }

    public static function notYours(): self
    {
        return new self('این آدرس متعلق به شما نیست.', 'address_not_owned', Response::HTTP_FORBIDDEN);
    }
}
