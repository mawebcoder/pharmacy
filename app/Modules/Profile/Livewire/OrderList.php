<?php

declare(strict_types=1);

namespace App\Modules\Profile\Livewire;

use App\Modules\Orders\Livewire\Concerns\HandlesOrderActions;
use App\Modules\Orders\Queries\CustomerOrders;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * «سفارش‌ها» (roadmap B9.9, S23): the customer's orders, newest first, as cards (number, date, status, total, items).
 * A pending order offers «پرداخت» (its open payment link is reused) until it expires; the others «سفارش دوباره».
 * Each card, and its «جزئیات سفارش» button, opens the order (OrderDetail), the only place to cancel it (owner,
 * 2026-11-17).
 */
final class OrderList extends Component
{
    use HandlesOrderActions;
    use Interactions;
    use WithPagination;

    public function render(CustomerOrders $orders): View
    {
        return view('profile::livewire.order-list', [
            'orders' => $orders->paginate($this->customerId()),
        ])->title('سفارش‌ها');
    }
}
