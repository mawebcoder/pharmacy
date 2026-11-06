<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\Demo\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Greeting extends Component
{
    public string $name = 'مهمان';

    public function render(): View
    {
        return view('demo::livewire.greeting');
    }
}
