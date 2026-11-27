<?php

declare(strict_types=1);

namespace App\Modules\Seo\Policies;

use App\Modules\Seo\Models\SeoRedirect;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Redirects (roadmap S38): no role is granted them, so only super_admin (through Shield) manages them.
 */
final class SeoRedirectPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:SeoRedirect');
    }

    public function view(Authorizable $admin, SeoRedirect $redirect): bool
    {
        return $admin->can('View:SeoRedirect');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:SeoRedirect');
    }

    public function update(Authorizable $admin, SeoRedirect $redirect): bool
    {
        return $admin->can('Update:SeoRedirect');
    }

    public function delete(Authorizable $admin, SeoRedirect $redirect): bool
    {
        return $admin->can('Delete:SeoRedirect');
    }
}
