<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Support\PersianDigits;

/**
 * A purchase document change that cannot be made (roadmap S30), with the reason for the admin.
 */
final class PurchaseRefused extends BaseException
{
    public static function orderNotPaid(): self
    {
        return new self('سند خرید فقط برای سفارش پرداخت‌شده‌ای که لغو نشده ثبت می‌شود.', 'purchase_order_not_paid');
    }

    public static function notThisOrder(): self
    {
        return new self('این کالا متعلق به همین سفارش نیست.', 'purchase_item_not_in_order');
    }

    public static function unknownSupplier(): self
    {
        return new self('تأمین‌کننده پیدا نشد.', 'purchase_unknown_supplier');
    }

    public static function badQuantity(): self
    {
        return new self('تعداد باید دست‌کم ۱ باشد و قیمت خرید بیشتر از صفر.', 'purchase_bad_quantity');
    }

    public static function moreThanOrdered(int $left): self
    {
        return new self('تعداد از باقی‌ماندهٔ این کالا بیشتر است (باقی‌مانده: '.PersianDigits::toPersian($left).').', 'purchase_more_than_ordered');
    }

    public static function belowHandled(int $handled): self
    {
        return new self('تعداد نمی‌تواند کمتر از تعداد دریافت‌شده یا کسری ثبت‌شده باشد ('.PersianDigits::toPersian($handled).').', 'purchase_below_handled');
    }

    public static function alreadyHandled(): self
    {
        return new self('ردیفی که دریافت یا کسری آن ثبت شده حذف نمی‌شود.', 'purchase_item_handled');
    }

    public static function locked(): self
    {
        return new self('ردیف‌های سند خرید فقط تا ۴۸ ساعت پس از ثبت آن قابل تغییر است.', 'purchase_locked');
    }

    public static function receivedTooMany(): self
    {
        return new self('مجموع دریافت‌شده و کسری از تعداد این ردیف بیشتر است.', 'purchase_received_too_many');
    }
}
