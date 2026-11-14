<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire\Account;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «سفارش‌ها» (roadmap B9.9): an empty state until orders exist; S29 replaces it with the order list.
 */
final class Orders extends Component
{
    public function render(): View
    {
        return view('identity::livewire.account.orders')->title('سفارش‌ها');
    }
}
