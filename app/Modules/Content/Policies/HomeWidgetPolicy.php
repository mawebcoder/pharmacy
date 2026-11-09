<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Modules\Content\Models\HomeWidget;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Home page widgets («چیدمان صفحه اصلی», roadmap D3.6, S13b).
 */
final class HomeWidgetPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:HomeWidget');
    }

    public function view(Authorizable $admin, HomeWidget $widget): bool
    {
        return $admin->can('View:HomeWidget');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:HomeWidget');
    }

    public function update(Authorizable $admin, HomeWidget $widget): bool
    {
        return $admin->can('Update:HomeWidget');
    }

    public function delete(Authorizable $admin, HomeWidget $widget): bool
    {
        return $admin->can('Delete:HomeWidget');
    }
}
