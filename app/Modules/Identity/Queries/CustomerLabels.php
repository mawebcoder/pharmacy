<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Modules\Identity\Models\Customer;

/**
 * «نام · موبایل» labels for customer ids in other modules' admin tables (e.g. coupon usages, S20).
 */
final readonly class CustomerLabels
{
    /**
     * @param  list<int>  $customerIds
     * @return array<int, string> customer id => label
     */
    public function for(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $labels = [];

        foreach (Customer::query()->whereKey(array_values(array_unique($customerIds)))->get() as $customer) {
            $name = trim(($customer->getFirstName() ?? '').' '.($customer->getLastName() ?? ''));
            $labels[$customer->getId()] = $name === '' ? $customer->getMobile() : $name.' · '.$customer->getMobile();
        }

        return $labels;
    }
}
