<?php

declare(strict_types=1);

namespace App\Modules\Orders\Policies;

use App\Modules\Orders\Models\OrderPurchase;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Purchase documents in the admin (roadmap S25). super_admin passes every check through Shield; operators open
 * documents, type their rows and record what arrived. Documents are opened from their order, never deleted.
 */
final class OrderPurchasePolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:OrderPurchase');
    }

    public function view(Authorizable $admin, OrderPurchase $purchase): bool
    {
        return $admin->can('View:OrderPurchase');
    }

    /**
     * Opening a document from its order.
     */
    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:OrderPurchase');
    }

    /**
     * Its rows, receiving and its note.
     */
    public function update(Authorizable $admin, OrderPurchase $purchase): bool
    {
        return $admin->can('Update:OrderPurchase');
    }
}
