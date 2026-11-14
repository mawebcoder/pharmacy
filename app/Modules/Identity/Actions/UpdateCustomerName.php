<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Customer;

/**
 * Saves the name a customer gives after their first sign-in (roadmap S17); blank parts are stored as null.
 */
final class UpdateCustomerName
{
    public function __invoke(Customer $customer, ?string $firstName, ?string $lastName): void
    {
        $customer
            ->setFirstName($this->clean($firstName))
            ->setLastName($this->clean($lastName))
            ->save();
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 60);
    }
}
