<?php

declare(strict_types=1);

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Gives the link to pay an order (owner, 2026-11-14: one payment link per order). While the order's last attempt is
 * pending and not expired its link is reused; otherwise a new attempt is opened at the gateway, lasting as long as
 * the order accepts payment. The order's payment attempts are locked meanwhile, so two clicks never open two links.
 */
final readonly class OpenPaymentLink
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * @throws GatewayUnavailable
     */
    #[\NoDiscard]
    public function __invoke(PaymentRequestData $request): string
    {
        return DB::transaction(function () use ($request): string {
            $open = Payment::query()
                ->where(Payment::COLUMN_ORDER_ID, $request->orderId)
                ->where(Payment::COLUMN_KIND, $request->kind)
                ->lockForUpdate()
                ->get()
                ->first(fn (Payment $payment): bool => $payment->isOpen() && $payment->getAmount() === $request->amount);

            if ($open instanceof Payment) {
                return $open->getUrl();
            }

            $link = $this->gateway->request($request);

            $payment = (new Payment)
                ->setOrderId($request->orderId)
                ->setKind($request->kind)
                ->setGateway($this->gateway->name())
                ->setAmount($request->amount)
                ->setAuthority($link->authority)
                ->setUrl($link->url)
                ->setStatus(PaymentAttemptStatus::Pending)
                ->setExpiresAt($request->expiresAt);
            $payment->save();

            return $payment->getUrl();
        });
    }
}
