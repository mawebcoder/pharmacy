<?php

declare(strict_types=1);

namespace App\Modules\Identity\Livewire;

use App\Modules\Identity\Actions\SetCustomerTheme;
use App\Modules\Identity\Enums\ThemePreference;
use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Support\StorefrontTheme;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The header's light/dark switch (owner, 2026-11-14). The page switches at once in the browser; this saves the choice
 * on the signed-in customer's account (as «اطلاعات حساب» does) and in a cookie, so guests keep it too.
 */
final class ThemeToggle extends Component
{
    public function choose(string $theme, SetCustomerTheme $setTheme): void
    {
        $this->skipRender();
        $preference = ThemePreference::tryFrom($theme);

        if ($preference === null || $preference === ThemePreference::System) {
            return;
        }

        $customer = auth('customer')->user();

        if ($customer instanceof Customer) {
            $setTheme($customer, $preference);
        }

        StorefrontTheme::remember($preference);
        $this->dispatch('theme-changed', theme: $preference->value);
    }

    public function render(): View
    {
        return view('identity::livewire.theme-toggle');
    }
}
