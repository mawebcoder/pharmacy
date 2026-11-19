<?php

declare(strict_types=1);

namespace App\Modules\Profile\Livewire;

use App\Modules\Orders\Actions\CancelOrderByCustomer;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Livewire\Concerns\HandlesOrderActions;
use App\Modules\Orders\Support\OrderCheckpoints;
use App\Modules\Payments\Queries\OrderPayments;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shipping\Exceptions\ParcelCheckTooSoon;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\ParcelTrackingStatus;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * One order in «سفارش‌ها» (owner, 2026-11-14; the full tracking page stays in S24): number, date, status with its
 * checkpoint tracker (OrderCheckpoints, owner 2026-11-16), items, address, totals and payment, with «پرداخت», «لغو سفارش» or «سفارش دوباره». Everything is read from
 * the order's own copies (roadmap D3.4 «Order pages read only these copies»), and only the owner can open it.
 */
final class OrderDetail extends Component
{
    use HandlesOrderActions;
    use Interactions;

    #[Locked]
    public int $orderId;

    /** What the last «استعلام از سرویس ارسال» said (S37). */
    public ?string $parcelNotice = null;

    public function mount(int $order): void
    {
        $this->orderId = $this->ownOrder($order)->getId();
    }

    /**
     * «لغو سفارش» is offered only here, not in the order list (owner, 2026-11-17).
     */
    public function cancel(int $orderId, CancelOrderByCustomer $cancelOrder): void
    {
        try {
            $cancelOrder($this->ownOrder($orderId), $this->customerId());
            $this->toast()->success('سفارش لغو شد؛ مبلغ پرداختی به حساب شما بازگردانده می‌شود.')->send();
        } catch (OrderRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();
        }
    }

    /**
     * «استعلام از سرویس ارسال» (owner, 2026-11-19): asks the carrier now, an hour after its last successful answer at
     * the soonest; the answer shows in the parcel box.
     */
    public function inquireParcel(ParcelTrackingStatus $parcels): void
    {
        $order = $this->ownOrder($this->orderId);

        if ($order->getStatus() !== OrderStatus::OnTheWay || blank($order->getTrackingCode())) {
            return;
        }

        try {
            $parcels->checkIfDue($order->getId(), (string) $order->getTrackingCode());
            $this->parcelNotice = 'آخرین وضعیت مرسوله از سرویس ارسال دریافت شد.';
        } catch (ParcelCheckTooSoon $tooSoon) {
            $this->parcelNotice = 'وضعیت مرسوله کمتر از یک ساعت پیش به‌روز شده است؛ '.PersianDigits::toPersian((string) $tooSoon->minutesLeft).' دقیقه دیگر دوباره امتحان کنید.';
        } catch (TipaxRequestFailed) {
            $this->parcelNotice = 'سرویس ارسال پاسخ نداد؛ کمی بعد دوباره امتحان کنید.';
        }
    }

    public function render(OrderPayments $payments, ParcelTrackingStatus $parcels): View
    {
        $order = $this->ownOrder($this->orderId)->load(['items', 'statusLogs']);
        // Tipax's parcel status while the order is on its way (S37); Tipax is asked at most once an hour.
        $parcel = $order->getStatus() === OrderStatus::OnTheWay && filled($order->getTrackingCode())
            ? $parcels->for($order->getId(), (string) $order->getTrackingCode())
            : null;

        return view('profile::livewire.order-detail', [
            'order' => $order,
            'checkpoints' => OrderCheckpoints::for($order),
            'reference' => $payments->paidReference($order->getId()),
            'parcel' => $parcel,
        ])->title('سفارش '.$order->getNumber());
    }
}
