<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Product;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Products with their variants, FAQs and links: editing any of them is an update of the product. Products are
 * soft-deleted (with their variants) and can be restored; there is no permanent delete.
 */
final class ProductPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Product');
    }

    public function view(Authorizable $admin, Product $product): bool
    {
        return $admin->can('View:Product');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Product');
    }

    public function update(Authorizable $admin, Product $product): bool
    {
        return $admin->can('Update:Product');
    }

    public function delete(Authorizable $admin, Product $product): bool
    {
        return $admin->can('Delete:Product');
    }

    public function restore(Authorizable $admin, Product $product): bool
    {
        return $admin->can('Restore:Product');
    }
}
