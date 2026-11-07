<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Category;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Categories are soft-deleted so they can be restored; there is no permanent delete. Reordering the tree is an update.
 */
final class CategoryPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Category');
    }

    public function view(Authorizable $admin, Category $category): bool
    {
        return $admin->can('View:Category');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Category');
    }

    public function update(Authorizable $admin, Category $category): bool
    {
        return $admin->can('Update:Category');
    }

    public function reorder(Authorizable $admin): bool
    {
        return $admin->can('Update:Category');
    }

    public function delete(Authorizable $admin, Category $category): bool
    {
        return $admin->can('Delete:Category');
    }

    public function restore(Authorizable $admin, Category $category): bool
    {
        return $admin->can('Restore:Category');
    }

    public function restoreAny(Authorizable $admin): bool
    {
        return $admin->can('Restore:Category');
    }
}
