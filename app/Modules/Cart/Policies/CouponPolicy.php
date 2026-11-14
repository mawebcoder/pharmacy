<?php

declare(strict_types=1);

namespace App\Modules\Cart\Policies;

use App\Modules\Cart\Models\Coupon;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Coupons (roadmap D3.4, S20). super_admin passes every check through Shield; operators may look them up.
 */
final class CouponPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Coupon');
    }

    public function view(Authorizable $admin, Coupon $coupon): bool
    {
        return $admin->can('View:Coupon');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Coupon');
    }

    public function update(Authorizable $admin, Coupon $coupon): bool
    {
        return $admin->can('Update:Coupon');
    }

    public function delete(Authorizable $admin, Coupon $coupon): bool
    {
        return $admin->can('Delete:Coupon');
    }

    public function restore(Authorizable $admin, Coupon $coupon): bool
    {
        return $admin->can('Restore:Coupon');
    }
}
