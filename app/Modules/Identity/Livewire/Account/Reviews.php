<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire\Account;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «نظرات من» (roadmap B9.9): an empty state until reviews exist; S13 fills it with the customer's reviews.
 */
final class Reviews extends Component
{
    public function render(): View
    {
        return view('identity::livewire.account.reviews')->title('نظرات من');
    }
}
