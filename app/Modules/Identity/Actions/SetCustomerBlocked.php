<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Customer;
use Illuminate\Support\Str;

/**
 * Blocks or unblocks a customer (roadmap D3.3). A blocked customer cannot sign in; blocking also ends their
 * «remember me» sign-ins and API tokens. The change is written to the activity log.
 */
final class SetCustomerBlocked
{
    public function __invoke(Customer $customer, bool $isBlocked): void
    {
        $customer->setIsBlocked($isBlocked);

        if ($isBlocked) {
            $customer->setRememberToken(Str::random(60));
            $customer->tokens()->delete();
        }

        $customer->save();
    }
}
