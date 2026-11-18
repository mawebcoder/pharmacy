<?php

declare(strict_types=1);

namespace App\Modules\Settings\Policies;

use App\Modules\Settings\Models\Pharmacist;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Pharmacists (roadmap S30): no role is granted them, so only super_admin (through Shield) manages them.
 */
final class PharmacistPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Pharmacist');
    }

    public function view(Authorizable $admin, Pharmacist $pharmacist): bool
    {
        return $admin->can('View:Pharmacist');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Pharmacist');
    }

    public function update(Authorizable $admin, Pharmacist $pharmacist): bool
    {
        return $admin->can('Update:Pharmacist');
    }

    public function delete(Authorizable $admin, Pharmacist $pharmacist): bool
    {
        return $admin->can('Delete:Pharmacist');
    }

    public function reorder(Authorizable $admin): bool
    {
        return $admin->can('Update:Pharmacist');
    }
}
