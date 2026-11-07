<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Attribute;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Attributes are soft-deleted and can be restored; there is no permanent delete. Editing values is an update of
 * the attribute.
 */
final class AttributePolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Attribute');
    }

    public function view(Authorizable $admin, Attribute $attribute): bool
    {
        return $admin->can('View:Attribute');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Attribute');
    }

    public function update(Authorizable $admin, Attribute $attribute): bool
    {
        return $admin->can('Update:Attribute');
    }

    public function delete(Authorizable $admin, Attribute $attribute): bool
    {
        return $admin->can('Delete:Attribute');
    }

    public function restore(Authorizable $admin, Attribute $attribute): bool
    {
        return $admin->can('Restore:Attribute');
    }
}
