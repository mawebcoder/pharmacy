<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Policies;

use App\Modules\Pricing\Models\Provider;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Suppliers and their prices (roadmap S14). Supplier prices are our costs: whoever can view suppliers sees them on
 * the SKU page; changing a supplier's prices needs Update:Provider.
 */
final class ProviderPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Provider');
    }

    public function view(Authorizable $admin, Provider $provider): bool
    {
        return $admin->can('View:Provider');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Provider');
    }

    public function update(Authorizable $admin, Provider $provider): bool
    {
        return $admin->can('Update:Provider');
    }

    public function delete(Authorizable $admin, Provider $provider): bool
    {
        return $admin->can('Delete:Provider');
    }
}
