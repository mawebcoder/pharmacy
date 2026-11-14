<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Modules\Identity\Models\Customer;

/**
 * A customer's name and mobile as an order keeps them (roadmap D3.4 customer_snapshot, S25).
 */
final readonly class CustomerContact
{
    /**
     * @return array{name: string|null, mobile: string}|null
     */
    public function for(int $customerId): ?array
    {
        $customer = Customer::query()->find($customerId);

        if (! $customer instanceof Customer) {
            return null;
        }

        $name = trim(($customer->getFirstName() ?? '').' '.($customer->getLastName() ?? ''));

        return ['name' => $name === '' ? null : $name, 'mobile' => $customer->getMobile()];
    }
}
