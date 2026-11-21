<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use App\Modules\Shared\Exceptions\BaseException;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Symfony\Component\HttpFoundation\Response;

/**
 * An order action the customer (or admin) cannot take now; shown as is.
 */
final class OrderRefused extends BaseException
{
    public static function statusChange(string $from, string $to): self
    {
        return new self('وضعیت سفارش از «'.$from.'» به «'.$to.'» تغییر نمی‌کند.', 'order_status_change_refused');
    }

    public static function notYours(): self
    {
        return new self('این سفارش متعلق به شما نیست.', 'order_not_owned', Response::HTTP_FORBIDDEN);
    }

    public static function cannotBePaid(): self
    {
        return new self('مهلت پرداخت این سفارش تمام شده است.', 'order_cannot_be_paid');
    }

    public static function paymentUnavailable(): self
    {
        return new self('اتصال به درگاه پرداخت ممکن نشد؛ چند دقیقه دیگر از «سفارش‌ها» دوباره تلاش کنید.', 'order_payment_unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
    }

    public static function notRefundable(): self
    {
        return new self('بازگشت وجه فقط برای سفارش لغوشده‌ای که در انتظار بازگشت وجه است ثبت می‌شود.', 'order_not_refundable');
    }

    public static function refundTooLarge(string $part, string $paid): self
    {
        return new self("مبلغ بازگشتی {$part} از مبلغ پرداختی آن ({$paid}) بیشتر است.", 'order_refund_too_large');
    }

    public static function emptyRefund(): self
    {
        return new self('مبلغی برای بازگشت وارد نشده است.', 'order_refund_empty');
    }

    public static function senderIncomplete(): self
    {
        return new self('اطلاعات فرستنده (نام، موبایل، آدرس انبار و شهر مبدأ) در «تنظیمات فروشگاه» کامل نیست.', 'order_sender_incomplete');
    }

    public static function notPacked(): self
    {
        return new self('این سفارش هنوز بسته‌بندی نشده است؛ «بسته‌بندی دوباره» را بزنید.', 'order_not_packed');
    }

    public static function carrierRefused(string $reason): self
    {
        return new self('ثبت در تیپاکس انجام نشد: '.$reason, 'order_carrier_refused', Response::HTTP_BAD_GATEWAY);
    }

    public static function notReturnable(): self
    {
        return new self('مرجوعی فقط برای سفارش پرداخت‌شده‌ای که هنوز ارسال نشده یا سفارش تحویل‌شده، و فقط برای کالاهایی که هنوز مرجوع نشده‌اند ثبت می‌شود.', 'order_not_returnable');
    }

    public static function returnEmpty(): self
    {
        return new self('دست‌کم یک کالا را برای مرجوعی انتخاب کنید.', 'order_return_empty');
    }

    public static function returnTooMany(string $title, int $remaining): self
    {
        return new self('از «'.$title.'» فقط '.PersianDigits::toPersian((string) $remaining).' عدد قابل مرجوع است.', 'order_return_too_many');
    }

    public static function returnNotDraft(): self
    {
        return new self('فقط مرجوعیِ پیش‌نویس تأیید می‌شود.', 'order_return_not_draft');
    }

    public static function returnPickedUp(): self
    {
        return new self('تیپاکس مرسوله مرجوعی را جمع‌آوری کرده است؛ این مرجوعی لغو نمی‌شود.', 'order_return_picked_up');
    }

    public static function differenceOwed(int $amount): self
    {
        return new self('مشتری هنوز '.Toman::format($amount).' تفاوت مبلغ ویرایش سفارش را پرداخت نکرده است؛ سفارش پس از پرداخت ارسال می‌شود.', 'order_difference_owed');
    }

    public static function returnWindowClosed(): self
    {
        return new self('درخواست مرجوعی فقط تا ۲۴ ساعت پس از تحویل سفارش ثبت می‌شود.', 'order_return_window_closed');
    }

    public static function returnReasonNotAllowed(): self
    {
        return new self('دلیل انتخاب‌شده برای مرجوعی معتبر نیست.', 'order_return_reason_not_allowed');
    }

    public static function returnNotReceivable(): self
    {
        return new self('دریافت در انبار پس از اعلام تحویل مرجوعی به انبار توسط تیپاکس ثبت می‌شود؛ ابتدا «استعلام از تیپاکس» را بزنید.', 'order_return_not_receivable');
    }

    public static function returnReceivedTooMany(string $title, int $listed): self
    {
        return new self('از «'.$title.'» در این ردیف فقط '.PersianDigits::toPersian((string) $listed).' عدد مرجوع شده است.', 'order_return_received_too_many');
    }

    public static function returnNotRefundable(): self
    {
        return new self('بازگشت وجه پس از ثبت دریافت مرجوعی در انبار ثبت می‌شود (برای کالای ارسال‌نشده، پس از تأیید).', 'order_return_not_refundable');
    }

    public static function returnAlreadyCancelled(): self
    {
        return new self('این مرجوعی قبلاً لغو شده است.', 'order_return_cancelled');
    }

    public static function carrierCancelRefused(string $reason): self
    {
        return new self('لغو در تیپاکس انجام نشد: '.$reason, 'order_carrier_cancel_refused', Response::HTTP_BAD_GATEWAY);
    }

    public static function notSourced(): self
    {
        return new self('تحویل به حمل فقط پس از دریافت کامل سند خرید این سفارش ممکن است.', 'order_not_sourced');
    }

    public static function nothingToReorder(): self
    {
        return new self('هیچ‌یک از کالاهای این سفارش اکنون موجود نیست.', 'order_nothing_to_reorder');
    }
}
