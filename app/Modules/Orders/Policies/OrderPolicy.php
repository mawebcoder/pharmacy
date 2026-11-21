<?php

declare(strict_types=1);

namespace App\Modules\Orders\Policies;

use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Orders in the admin (roadmap S24). super_admin passes every check through Shield; operators view orders and move
 * their status (cancelling included). Recording a refund is money going out, so it is left to super admins.
 * Orders are never created or deleted in the admin.
 */
final class OrderPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Order');
    }

    public function view(Authorizable $admin, Order $order): bool
    {
        return $admin->can('View:Order');
    }

    /**
     * «ثبت سفارش» for a customer in the panel (owner, 2026-11-19).
     */
    public function create(Authorizable $admin): bool
    {
        return $admin->can('Create:Order');
    }

    /**
     * Status changes, the tracking code and the admin note.
     */
    public function update(Authorizable $admin, Order $order): bool
    {
        return $admin->can('Update:Order');
    }

    public function refund(Authorizable $admin, Order $order): bool
    {
        return $admin->can('Refund:Order');
    }
}
