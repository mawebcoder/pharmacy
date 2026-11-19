<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Policies;

use App\Modules\Shipping\Models\ShippingFallbackRate;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * «نرخ ارسال جایگزین» (roadmap S31): prices, so super admins only unless a role is given the permissions.
 */
final class ShippingFallbackRatePolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:ShippingFallbackRate');
    }

    public function view(Authorizable $admin, ShippingFallbackRate $record): bool
    {
        return $admin->can('View:ShippingFallbackRate');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:ShippingFallbackRate');
    }

    public function update(Authorizable $admin, ShippingFallbackRate $record): bool
    {
        return $admin->can('Update:ShippingFallbackRate');
    }

    public function delete(Authorizable $admin, ShippingFallbackRate $record): bool
    {
        return $admin->can('Delete:ShippingFallbackRate');
    }
}
