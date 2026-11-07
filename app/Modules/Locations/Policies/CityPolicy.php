<?php

declare(strict_types=1);

namespace App\Modules\Locations\Policies;

use App\Modules\Locations\Models\City;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Cities come from the official dataset: staff may only view them and switch delivery on or off.
 */
final class CityPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:City');
    }

    public function view(Authorizable $admin, City $city): bool
    {
        return $admin->can('View:City');
    }

    public function update(Authorizable $admin, City $city): bool
    {
        return $admin->can('Update:City');
    }
}
