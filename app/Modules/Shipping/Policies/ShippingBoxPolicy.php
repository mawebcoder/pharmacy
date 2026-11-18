<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Policies;

use App\Modules\Shipping\Models\ShippingBox;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Packing setup (roadmap S31): no role is granted it, so only super_admin (through Shield) manages it. Nothing is
 * deleted: boxes are switched off and SKUs point at size types.
 */
final class ShippingBoxPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:ShippingBox');
    }

    public function view(Authorizable $admin, ShippingBox $record): bool
    {
        return $admin->can('View:ShippingBox');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:ShippingBox');
    }

    public function update(Authorizable $admin, ShippingBox $record): bool
    {
        return $admin->can('Update:ShippingBox');
    }

    public function delete(Authorizable $admin, ShippingBox $record): bool
    {
        return false;
    }

    public function reorder(Authorizable $admin): bool
    {
        return $admin->can('Update:ShippingBox');
    }
}
