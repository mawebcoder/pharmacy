<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Exceptions\RefundRefused;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * «رد» on a refund record (owner, 2026-11-19): with a reason, only while it waits for approval; written to the activity
 * log. The customer is not texted.
 */
final readonly class RejectRefund
{
    /**
     * @throws RefundRefused
     */
    public function __invoke(Payment $refund, string $reason, int $adminId): void
    {
        DB::transaction(function () use ($refund, $reason, $adminId): void {
            $refund = self::waiting($refund);
            $refund->setStatus(PaymentAttemptStatus::Failed)
                ->setFailureReason(trim($reason))
                ->setFailedAt(now())
                ->setDecidedBy($adminId)
                ->setDecidedAt(now())
                ->save();

            activity('payments')->performedOn($refund)->causedBy(auth('admin')->user())
                ->withProperties(['amount' => $refund->getAmount(), 'reason' => trim($reason)])
                ->log('بازگشت وجه رد شد');
        });
    }

    /**
     * @throws RefundRefused
     */
    private static function waiting(Payment $refund): Payment
    {
        $refund = Payment::query()->lockForUpdate()->findOrFail($refund->getId());

        if ($refund->getKind() !== PaymentKind::Refund || $refund->getStatus() !== PaymentAttemptStatus::Pending) {
            throw RefundRefused::alreadyDecided();
        }

        return $refund;
    }
}
