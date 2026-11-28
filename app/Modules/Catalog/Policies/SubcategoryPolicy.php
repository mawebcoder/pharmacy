<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Subcategory;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Subcategories are part of their category (owner, 2026-11-15), so they use the category permissions: whoever may
 * see categories sees them, and changing one (adding, moving, reordering, deleting, restoring) is an update of a
 * category. They have no Shield permissions of their own (config/filament-shield.php excludes SubcategoryResource).
 */
final class SubcategoryPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Category');
    }

    public function view(Authorizable $admin, Subcategory $subcategory): bool
    {
        return $admin->can('View:Category');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Update:Category');
    }

    public function update(Authorizable $admin, Subcategory $subcategory): bool
    {
        return $admin->can('Update:Category');
    }

    public function reorder(Authorizable $admin): bool
    {
        return $admin->can('Update:Category');
    }

    public function delete(Authorizable $admin, Subcategory $subcategory): bool
    {
        return $admin->can('Update:Category');
    }

    public function restore(Authorizable $admin, Subcategory $subcategory): bool
    {
        return $admin->can('Update:Category');
    }

    public function restoreAny(Authorizable $admin): bool
    {
        return $admin->can('Update:Category');
    }
}
