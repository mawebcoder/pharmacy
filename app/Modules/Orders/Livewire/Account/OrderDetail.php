<?php

declare(strict_types=1);

namespace App\Modules\Orders\Livewire\Account;

use App\Modules\Orders\Livewire\Concerns\HandlesOrderActions;
use App\Modules\Payments\Queries\OrderPayments;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * One order in «سفارش‌ها» (owner, 2026-11-14; the full tracking page stays in S29): number, date, status with its
 * history, items, address, totals and payment, with «پرداخت», «لغو سفارش» or «سفارش دوباره». Everything is read from
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

    public function render(OrderPayments $payments): View
    {
        $order = $this->ownOrder($this->orderId)->load(['items', 'statusLogs']);

        return view('orders::livewire.account.order-detail', [
            'order' => $order,
            'reference' => $payments->paidReference($order->getId()),
        ])->title('سفارش '.$order->getNumber());
    }
}
