<?php

declare(strict_types=1);

namespace App\Modules\Profile\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «اطلاعات حساب» (roadmap B9.9): the account frame around Identity's profile form.
 */
final class AccountDetails extends Component
{
    public function render(): View
    {
        return view('profile::livewire.account-details')->title('اطلاعات حساب');
    }
}
