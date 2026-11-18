<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use App\Modules\Payments\Gateways\Zarinpal\ZarinpalError;
use RuntimeException;
use Throwable;

/**
 * Zarinpal refused a call or could not be reached (roadmap D7.3). zarinpalCode is Zarinpal's code when it answered
 * with one (e.g. −50 amount mismatch, −51 payment failed), else null. The message is Persian and has no secrets.
 */
final class ZarinpalRequestFailed extends RuntimeException
{
    public function __construct(public readonly ?int $zarinpalCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function refused(int $code, ?Throwable $previous = null): self
    {
        return new self($code, ZarinpalError::messageFor($code), $previous);
    }

    public static function unreachable(?Throwable $previous = null): self
    {
        return new self(null, 'درگاه زرین‌پال در دسترس نیست.', $previous);
    }

    public static function notConfigured(): self
    {
        return new self(null, 'کد پذیرنده زرین‌پال تنظیم نشده است (ZARINPAL_MERCHANT_ID).');
    }
}
