<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Modules\Content\Models\MenuItem;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * A menu's links (roadmap S29): whoever may update menus adds, changes, reorders and removes them.
 */
final class MenuItemPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('View:Menu');
    }

    public function view(Authorizable $admin, MenuItem $item): bool
    {
        return $admin->can('View:Menu');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Update:Menu');
    }

    public function update(Authorizable $admin, MenuItem $item): bool
    {
        return $admin->can('Update:Menu');
    }

    public function delete(Authorizable $admin, MenuItem $item): bool
    {
        return $admin->can('Update:Menu');
    }

    public function reorder(Authorizable $admin): bool
    {
        return $admin->can('Update:Menu');
    }
}
