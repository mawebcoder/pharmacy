<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Brand;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Brands are soft-deleted so products and old links can be restored; there is no permanent delete.
 */
final class BrandPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Brand');
    }

    public function view(Authorizable $admin, Brand $brand): bool
    {
        return $admin->can('View:Brand');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Brand');
    }

    public function update(Authorizable $admin, Brand $brand): bool
    {
        return $admin->can('Update:Brand');
    }

    public function delete(Authorizable $admin, Brand $brand): bool
    {
        return $admin->can('Delete:Brand');
    }

    public function deleteAny(Authorizable $admin): bool
    {
        return $admin->can('Delete:Brand');
    }

    public function restore(Authorizable $admin, Brand $brand): bool
    {
        return $admin->can('Restore:Brand');
    }

    public function restoreAny(Authorizable $admin): bool
    {
        return $admin->can('Restore:Brand');
    }
}
