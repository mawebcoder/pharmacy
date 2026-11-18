<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\FlashDeal;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Flash deals (roadmap S28) change what customers pay, so they are left to super admins (Shield lets super_admin pass
 * every check); other roles get the permissions only when granted in the admin.
 */
final class FlashDealPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:FlashDeal');
    }

    public function view(Authorizable $admin, FlashDeal $deal): bool
    {
        return $admin->can('View:FlashDeal');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:FlashDeal');
    }

    public function update(Authorizable $admin, FlashDeal $deal): bool
    {
        return $admin->can('Update:FlashDeal');
    }

    public function delete(Authorizable $admin, FlashDeal $deal): bool
    {
        return $admin->can('Delete:FlashDeal');
    }
}
