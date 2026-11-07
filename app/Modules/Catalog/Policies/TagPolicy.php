<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Tag;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Tags are soft-deleted and can be restored; there is no permanent delete.
 */
final class TagPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Tag');
    }

    public function view(Authorizable $admin, Tag $tag): bool
    {
        return $admin->can('View:Tag');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Tag');
    }

    public function update(Authorizable $admin, Tag $tag): bool
    {
        return $admin->can('Update:Tag');
    }

    public function delete(Authorizable $admin, Tag $tag): bool
    {
        return $admin->can('Delete:Tag');
    }

    public function restore(Authorizable $admin, Tag $tag): bool
    {
        return $admin->can('Restore:Tag');
    }
}
