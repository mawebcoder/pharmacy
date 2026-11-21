<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Modules\Identity\Models\Customer;
use App\Modules\Shared\Support\PersianDigits;

/**
 * «نام · موبایل» labels for customer ids in other modules' admin tables (e.g. coupon usages, S22).
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

    /**
     * Customers matching a mobile or name, for «ثبت سفارش» in the panel (owner, 2026-11-19): at most 20.
     *
     * @return array<int, string> customer id => label
     */
    public function search(string $term): array
    {
        $term = trim(PersianDigits::toLatin($term));

        if ($term === '') {
            return [];
        }

        $ids = Customer::query()
            ->where(fn ($match) => $match
                ->where(Customer::COLUMN_MOBILE, 'like', '%'.ltrim($term, '+').'%')
                ->orWhere(Customer::COLUMN_FIRST_NAME, 'like', "%{$term}%")
                ->orWhere(Customer::COLUMN_LAST_NAME, 'like', "%{$term}%"))
            ->orderByDesc(Customer::COLUMN_ID)
            ->limit(20)
            ->pluck(Customer::COLUMN_ID)
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $this->for(array_values($ids));
    }
}
