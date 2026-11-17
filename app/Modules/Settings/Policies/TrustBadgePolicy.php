<?php

declare(strict_types=1);

namespace App\Modules\Settings\Policies;

use App\Modules\Settings\Models\TrustBadge;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Trust badges (roadmap S35): no role is granted them, so only super_admin (through Shield) manages them.
 */
final class TrustBadgePolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:TrustBadge');
    }

    public function view(Authorizable $admin, TrustBadge $badge): bool
    {
        return $admin->can('View:TrustBadge');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:TrustBadge');
    }

    public function update(Authorizable $admin, TrustBadge $badge): bool
    {
        return $admin->can('Update:TrustBadge');
    }

    public function delete(Authorizable $admin, TrustBadge $badge): bool
    {
        return $admin->can('Delete:TrustBadge');
    }

    public function reorder(Authorizable $admin): bool
    {
        return $admin->can('Update:TrustBadge');
    }
}
