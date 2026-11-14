<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\ThemePreference;
use App\Modules\Identity\Models\Customer;

/**
 * Saves the storefront theme a customer picks on «اطلاعات حساب» (owner, 2026-11-14).
 */
final class SetCustomerTheme
{
    public function __invoke(Customer $customer, ThemePreference $theme): void
    {
        $customer->setTheme($theme)->save();
    }
}
