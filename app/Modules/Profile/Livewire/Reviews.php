<?php

declare(strict_types=1);

namespace App\Modules\Profile\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «نظرات من» (roadmap B9.9): an empty state until reviews exist; S13 fills it with the customer's reviews.
 */
final class Reviews extends Component
{
    public function render(): View
    {
        return view('profile::livewire.reviews')->title('نظرات من');
    }
}
