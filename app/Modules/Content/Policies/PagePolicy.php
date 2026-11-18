<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Modules\Content\Models\Page;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Static pages (roadmap D3.6, S29). super_admin passes every check through Shield.
 */
final class PagePolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Page');
    }

    public function view(Authorizable $admin, Page $page): bool
    {
        return $admin->can('View:Page');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Page');
    }

    public function update(Authorizable $admin, Page $page): bool
    {
        return $admin->can('Update:Page');
    }

    public function delete(Authorizable $admin, Page $page): bool
    {
        return $admin->can('Delete:Page');
    }

    public function restore(Authorizable $admin, Page $page): bool
    {
        return $admin->can('Restore:Page');
    }
}
