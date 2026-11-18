<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Modules\Content\Models\Menu;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Site menus (roadmap S29): the five menus exist from the migration, so they are only viewed and updated (heading and
 * links). super_admin passes every check through Shield.
 */
final class MenuPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Menu');
    }

    public function view(Authorizable $admin, Menu $menu): bool
    {
        return $admin->can('View:Menu');
    }

    public function create(Authorizable $admin): bool
    {
        return false;
    }

    public function update(Authorizable $admin, Menu $menu): bool
    {
        return $admin->can('Update:Menu');
    }

    public function delete(Authorizable $admin, Menu $menu): bool
    {
        return false;
    }
}
