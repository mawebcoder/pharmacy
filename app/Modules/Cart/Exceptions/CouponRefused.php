<?php

declare(strict_types=1);

namespace App\Modules\Cart\Exceptions;

use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\Toman;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Why a coupon code cannot be used on this cart (roadmap S22); shown to the customer as is.
 */
final class CouponRefused extends BaseException
{
    public static function notFound(): self
    {
        return new self('کد تخفیف معتبر نیست.', 'coupon_not_found');
    }

    public static function signInRequired(): self
    {
        return new self('برای استفاده از کد تخفیف ابتدا وارد حساب خود شوید.', 'coupon_sign_in_required', Response::HTTP_UNAUTHORIZED);
    }

    public static function notStarted(Carbon $startsAt): self
    {
        return new self('این کد تخفیف از '.JalaliDate::display($startsAt).' فعال می‌شود.', 'coupon_not_started');
    }

    public static function expired(): self
    {
        return new self('مهلت استفاده از این کد تخفیف تمام شده است.', 'coupon_expired');
    }

    public static function firstOrderOnly(): self
    {
        return new self('این کد تخفیف فقط برای اولین خرید است.', 'coupon_first_order_only');
    }

    public static function exhausted(): self
    {
        return new self('ظرفیت استفاده از این کد تخفیف تمام شده است.', 'coupon_exhausted');
    }

    public static function alreadyUsed(): self
    {
        return new self('شما پیش‌تر از این کد تخفیف استفاده کرده‌اید.', 'coupon_already_used');
    }

    public static function noCoveredItems(): self
    {
        return new self('این کد تخفیف شامل کالاهای سبد شما نمی‌شود.', 'coupon_no_covered_items');
    }

    public static function belowMinimum(int $minimum, int $covered, bool $limited): self
    {
        $what = $limited ? 'کالاهای مشمول این کد' : 'سبد';

        return new self('حداقل خرید '.$what.' '.Toman::format($minimum).' است؛ '.Toman::format($minimum - $covered).' دیگر اضافه کنید.', 'coupon_below_minimum');
    }

    public static function cartEmpty(): self
    {
        return new self('سبد خرید شما خالی است.', 'coupon_cart_empty');
    }
}
