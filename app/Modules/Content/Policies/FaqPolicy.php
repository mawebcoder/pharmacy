<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Modules\Content\Models\Faq;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Questions on /faq (roadmap S34). super_admin passes every check through Shield.
 */
final class FaqPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Faq');
    }

    public function view(Authorizable $admin, Faq $faq): bool
    {
        return $admin->can('View:Faq');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Faq');
    }

    public function update(Authorizable $admin, Faq $faq): bool
    {
        return $admin->can('Update:Faq');
    }

    public function delete(Authorizable $admin, Faq $faq): bool
    {
        return $admin->can('Delete:Faq');
    }

    public function reorder(Authorizable $admin): bool
    {
        return $admin->can('Update:Faq');
    }
}
