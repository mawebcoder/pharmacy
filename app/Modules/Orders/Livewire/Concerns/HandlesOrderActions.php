<?php

declare(strict_types=1);

namespace App\Modules\Orders\Livewire\Concerns;

use App\Modules\Orders\Actions\CancelOrderByCustomer;
use App\Modules\Orders\Actions\PayOrder;
use App\Modules\Orders\Actions\ReorderItems;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;

/**
 * «پرداخت», «لغو سفارش» and «سفارش دوباره» for the customer's own orders (S25), shared by the order list and the
 * payment result page. Refusals show as a warning toast.
 */
trait HandlesOrderActions
{
    public function pay(int $orderId, PayOrder $payOrder): void
    {
        try {
            $this->redirect($payOrder($this->ownOrder($orderId), $this->customerId()));
        } catch (OrderRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();
        }
    }

    public function cancel(int $orderId, CancelOrderByCustomer $cancelOrder): void
    {
        try {
            $cancelOrder($this->ownOrder($orderId), $this->customerId());
            $this->toast()->success('سفارش لغو شد؛ مبلغ پرداختی به حساب شما بازگردانده می‌شود.')->send();
        } catch (OrderRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();
        }
    }

    public function reorder(int $orderId, ReorderItems $reorderItems): void
    {
        try {
            $result = $reorderItems($this->ownOrder($orderId), $this->customerId());
        } catch (OrderRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();

            return;
        }

        session()->flash('reorder.message', $result->message());
        $this->redirectRoute('cart', navigate: true);
    }

    protected function ownOrder(int $orderId): Order
    {
        return Order::query()->ownedBy($this->customerId())->findOrFail($orderId);
    }

    protected function customerId(): int
    {
        return (int) auth('customer')->user()?->getAuthIdentifier();
    }
}
