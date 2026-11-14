<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\CustomerProfileData;
use App\Modules\Identity\Models\Customer;

/**
 * Saves the customer's own profile from «اطلاعات حساب» (roadmap D3.3, S18); the national id is stored encrypted.
 */
final class UpdateCustomerProfile
{
    public function __invoke(Customer $customer, CustomerProfileData $data): void
    {
        $customer
            ->setFirstName($data->firstName)
            ->setLastName($data->lastName)
            ->setNationalId($data->nationalId)
            ->setEmail($data->email)
            ->setBirthDate($data->birthDate)
            ->setGender($data->gender)
            ->save();
    }
}
