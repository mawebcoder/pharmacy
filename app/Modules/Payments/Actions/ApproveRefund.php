<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentKind;
use App\Modules\Payments\Events\RefundApproved;
use App\Modules\Payments\Exceptions\RefundRefused;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * «تأیید بازگشت وجه» on a refund record (owner, 2026-11-19): records the bank transfer reference (a cancelled order's
 * items and shipping may be lowered, never raised), texts the customer and settles a return or a cancelled order. Only a
 * refund waiting for approval is approved, once; written to the activity log.
 */
final readonly class ApproveRefund
{
    /**
     * @throws RefundRefused
     */
    public function __invoke(Payment $refund, string $reference, int $adminId, ?int $itemsAmount = null, ?int $shippingAmount = null): void
    {
        DB::transaction(function () use ($refund, $reference, $adminId, $itemsAmount, $shippingAmount): void {
            $refund = $this->waiting($refund);
            $details = $refund->getDetails();

            // A cancelled order's refund may be lowered on approval, never raised (items and shipping each).
            if ($details !== [] && ($itemsAmount !== null || $shippingAmount !== null)) {
                $items = $itemsAmount ?? (int) ($details['items'] ?? 0);
                $shipping = $shippingAmount ?? (int) ($details['shipping'] ?? 0);

                if ($items < 0 || $shipping < 0 || $items > (int) ($details['items'] ?? 0) || $shipping > (int) ($details['shipping'] ?? 0)) {
                    throw RefundRefused::tooLarge($refund->getAmount());
                }

                if ($items + $shipping === 0) {
                    throw RefundRefused::empty();
                }

                $refund->setDetails(['items' => $items, 'shipping' => $shipping])->setAmount($items + $shipping);
            }

            $refund->setStatus(PaymentAttemptStatus::Paid)
                ->setRefId(trim($reference))
                ->setVerifiedAt(now())
                ->setDecidedBy($adminId)
                ->setDecidedAt(now())
                ->save();

            activity('payments')->performedOn($refund)->causedBy(auth('admin')->user())
                ->withProperties(['amount' => $refund->getAmount(), 'reference' => trim($reference)])
                ->log('بازگشت وجه تأیید شد');

            RefundApproved::dispatch($refund->getId(), $refund->getOrderId(), $refund->getOrderReturnId(), $refund->getAmount(), trim($reference), $refund->getRefundSource(), $refund->getDetails());
        });
    }

    /**
     * Approving by id, for other modules (a return's page).
     *
     * @throws RefundRefused
     */
    public function byId(int $refundId, string $reference, int $adminId): void
    {
        $this($this->load($refundId), $reference, $adminId);
    }

    private function load(int $refundId): Payment
    {
        return Payment::query()->findOrFail($refundId);
    }

    /**
     * @throws RefundRefused
     */
    private function waiting(Payment $refund): Payment
    {
        $refund = Payment::query()->lockForUpdate()->findOrFail($refund->getId());

        if ($refund->getKind() !== PaymentKind::Refund || $refund->getStatus() !== PaymentAttemptStatus::Pending) {
            throw RefundRefused::alreadyDecided();
        }

        return $refund;
    }
}
