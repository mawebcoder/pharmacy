<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\ProductCollection;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Collections (roadmap S13b). The system collection «محصولات محبوب» can be edited but never deleted.
 */
final class ProductCollectionPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:ProductCollection');
    }

    public function view(Authorizable $admin, ProductCollection $collection): bool
    {
        return $admin->can('View:ProductCollection');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:ProductCollection');
    }

    public function update(Authorizable $admin, ProductCollection $collection): bool
    {
        return $admin->can('Update:ProductCollection');
    }

    public function delete(Authorizable $admin, ProductCollection $collection): bool
    {
        return ! $collection->isSystem() && $admin->can('Delete:ProductCollection');
    }

    public function restore(Authorizable $admin, ProductCollection $collection): bool
    {
        return $admin->can('Restore:ProductCollection');
    }
}
