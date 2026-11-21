<?php

declare(strict_types=1);

namespace App\Modules\Payments\Policies;

use App\Modules\Payments\Models\Payment;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * «پرداخت‌ها» and «بازگشت وجه‌ها» in the admin (owner, 2026-11-19). super_admin passes every check through Shield;
 * operators see payments and refunds; approving or rejecting a refund needs «Approve:Refund» (super admins).
 * Gateway payments are never approved by hand.
 */
final class PaymentPolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:Payment');
    }

    public function view(Authorizable $admin, Payment $payment): bool
    {
        return $admin->can('View:Payment');
    }

    public function approveRefund(Authorizable $admin, Payment $payment): bool
    {
        return $admin->can('Approve:Refund');
    }
}
