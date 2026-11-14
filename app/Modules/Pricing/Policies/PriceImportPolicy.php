<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Policies;

use App\Modules\Pricing\Models\PriceImport;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Supplier price imports (roadmap S16): super admins by default, like supplier costs.
 */
final class PriceImportPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:PriceImport');
    }

    public function view(Authorizable $admin, PriceImport $import): bool
    {
        return $admin->can('View:PriceImport');
    }

    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:PriceImport');
    }

    public function update(Authorizable $admin, PriceImport $import): bool
    {
        return $admin->can('Update:PriceImport');
    }
}
