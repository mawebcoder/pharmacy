<?php

declare(strict_types=1);

namespace App\Modules\Orders\Livewire\Account;

use App\Modules\Orders\Actions\CancelOrderByCustomer;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Livewire\Concerns\HandlesOrderActions;
use App\Modules\Orders\Support\OrderCheckpoints;
use App\Modules\Payments\Queries\OrderPayments;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * One order in «سفارش‌ها» (owner, 2026-11-14; the full tracking page stays in S29): number, date, status with its
 * checkpoint tracker (OrderCheckpoints, owner 2026-11-16), items, address, totals and payment, with «پرداخت», «لغو سفارش» or «سفارش دوباره». Everything is read from
 * the order's own copies (roadmap D3.4 «Order pages read only these copies»), and only the owner can open it.
 */
final class OrderDetail extends Component
{
    use HandlesOrderActions;
    use Interactions;

    #[Locked]
    public int $orderId;

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

    public function render(OrderPayments $payments): View
    {
        $order = $this->ownOrder($this->orderId)->load(['items', 'statusLogs']);

        return view('orders::livewire.account.order-detail', [
            'order' => $order,
            'checkpoints' => OrderCheckpoints::for($order),
            'reference' => $payments->paidReference($order->getId()),
        ])->title('سفارش '.$order->getNumber());
    }
}
