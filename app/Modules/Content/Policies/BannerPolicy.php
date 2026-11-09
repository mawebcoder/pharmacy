<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Modules\Content\Models\Banner;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * The banner library (roadmap D3.6, S13b).
 */
final class BannerPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Banner');
    }

    public function view(Authorizable $admin, Banner $banner): bool
    {
        return $admin->can('View:Banner');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Banner');
    }

    public function update(Authorizable $admin, Banner $banner): bool
    {
        return $admin->can('Update:Banner');
    }

    public function delete(Authorizable $admin, Banner $banner): bool
    {
        return $admin->can('Delete:Banner');
    }

    public function restore(Authorizable $admin, Banner $banner): bool
    {
        return $admin->can('Restore:Banner');
    }
}
