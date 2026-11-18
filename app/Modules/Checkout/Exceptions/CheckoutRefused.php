<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Exceptions;

use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Support\Toman;

/**
 * Why «پرداخت» did not create an order (roadmap S23, owner 2026-11-14: everything is checked again at that moment);
 * checkout reloads with the new figures and shows this.
 */
final class CheckoutRefused extends BaseException
{
    public static function cartEmpty(): self
    {
        return new self('سبد خرید شما خالی است.', 'checkout_cart_empty');
    }

    /**
     * @param  list<string>  $names
     */
    public static function unavailable(array $names): self
    {
        return new self('این کالاها دیگر قابل خرید نیستند؛ از سبد حذفشان کنید: '.implode('، ', $names), 'checkout_items_unavailable');
    }

    public static function totalChanged(int $was, int $now): self
    {
        return new self('مبلغ سفارش از '.Toman::format($was).' به '.Toman::format($now).' تغییر کرد؛ مبلغ جدید را بررسی و دوباره «پرداخت» را بزنید.', 'checkout_total_changed');
    }

    public static function couponInvalid(string $reason): self
    {
        return new self('کد تخفیف دیگر معتبر نیست: '.$reason, 'checkout_coupon_invalid');
    }

    public static function noAddress(): self
    {
        return new self('برای ثبت سفارش یک آدرس انتخاب یا اضافه کنید.', 'checkout_no_address');
    }

    public static function addressNotDeliverable(): self
    {
        return new self('ارسال به شهر این آدرس فعلاً ممکن نیست؛ آدرس دیگری انتخاب کنید.', 'checkout_address_not_deliverable');
    }

    public static function dealEnded(string $reason): self
    {
        return new self($reason, 'checkout_flash_deal_ended');
    }

    public static function busy(): self
    {
        return new self('سفارش شما در حال ثبت است؛ چند لحظه صبر کنید.', 'checkout_busy');
    }
}
