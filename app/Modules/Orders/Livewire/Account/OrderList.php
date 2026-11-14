<?php

declare(strict_types=1);

namespace App\Modules\Orders\Livewire\Account;

use App\Modules\Orders\Livewire\Concerns\HandlesOrderActions;
use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * «سفارش‌ها» (roadmap B9.9, S25): the customer's orders, newest first, as cards (number, date, status, total, items).
 * A pending order offers «پرداخت» (its open payment link is reused) until it expires; a paid one «لغو سفارش»; the
 * others «سفارش دوباره». The order page with its timeline comes with S29.
 */
final class OrderList extends Component
{
    use HandlesOrderActions;
    use Interactions;
    use WithPagination;

    public function render(): View
    {
        return view('orders::livewire.account.order-list', [
            'orders' => Order::query()
                ->ownedBy($this->customerId())
                ->with('items')
                ->latest(Order::COLUMN_ID)
                ->paginate(10),
        ])->title('سفارش‌ها');
    }
}
