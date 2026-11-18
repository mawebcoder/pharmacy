<?php

declare(strict_types=1);

namespace App\Modules\Profile\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «آدرس‌ها» (roadmap B9.9, D3.3): the account frame around Identity's address book, which checkout also uses.
 */
final class Addresses extends Component
{
    public function render(): View
    {
        return view('profile::livewire.addresses')->title('آدرس‌ها');
    }
}
