<?php

declare(strict_types=1);

namespace App\Modules\Orders\Livewire;

use App\Modules\Orders\Livewire\Concerns\HandlesOrderActions;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Queries\OrderPayments;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * Where the customer lands after the gateway (roadmap B9.8, S25): paid (order number with copy, payment reference,
 * «پیگیری سفارش»), not paid yet (the reason and «تلاش دوباره» until the order expires) or expired («سفارش دوباره»).
 * Only the order's owner sees it.
 */
final class PaymentResult extends Component
{
    use HandlesOrderActions;
    use Interactions;

    public int $orderId;

    public function mount(int $order): void
    {
        $this->orderId = $this->ownOrder($order)->getId();
    }

    public function render(OrderPayments $payments): View
    {
        $order = $this->ownOrder($this->orderId);

        return view('orders::livewire.payment-result', [
            'order' => $order,
            'failureReason' => $payments->lastFailureReason($order->getId()),
            'reference' => $payments->paidReference($order->getId()),
        ])->title('نتیجه پرداخت');
    }
}
