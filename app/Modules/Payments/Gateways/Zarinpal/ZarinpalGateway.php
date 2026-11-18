<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Zarinpal;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentLinkData;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Data\PaymentVerificationData;
use App\Modules\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Payments\Exceptions\ZarinpalRequestFailed;
use App\Modules\Payments\Models\Payment;

/**
 * Zarinpal (roadmap D7, S36) behind the PaymentGateway contract: opens a payment in rial and sends the customer to
 * StartPay; when they come back, «NOK» is a failure without asking Zarinpal, and «OK» is trusted only after verify with
 * the amount the payment was opened for (100 paid now, 101 already verified). When Zarinpal cannot be reached, verify
 * throws GatewayUnavailable and the payment stays pending for ReconcileZarinpalPayments.
 */
final readonly class ZarinpalGateway implements PaymentGateway
{
    public const string NAME = 'zarinpal';

    public const string CANCELLED = 'پرداخت لغو شد یا ناموفق بود.';

    public function __construct(
        private ZarinpalClient $client,
        private ?string $callbackUrl,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function request(PaymentRequestData $request): PaymentLinkData
    {
        try {
            $payment = $this->client->request(
                amount: $request->amount,
                description: $request->description,
                callbackUrl: $this->callbackUrl ?: route('payments.callback'),
                mobile: $request->mobile !== '' ? $request->mobile : null,
            );
        } catch (ZarinpalRequestFailed $failed) {
            throw GatewayUnavailable::because($failed->getMessage(), $failed);
        }

        return new PaymentLinkData($payment['authority'], $payment['url']);
    }

    public function verify(Payment $payment, array $query): PaymentVerificationData
    {
        if (($query['Status'] ?? null) !== 'OK') {
            return new PaymentVerificationData(paid: false, failureReason: self::CANCELLED);
        }

        return $this->confirm($payment->getAmount(), $payment->getAuthority());
    }

    /**
     * Verifies an authority with its amount (also used by reconciliation).
     *
     * @throws GatewayUnavailable when Zarinpal cannot be reached; the payment must stay as it is
     */
    public function confirm(int $amount, string $authority): PaymentVerificationData
    {
        try {
            $verified = $this->client->verify($amount, $authority);
        } catch (ZarinpalRequestFailed $failed) {
            if ($failed->zarinpalCode === null) {
                throw GatewayUnavailable::because($failed->getMessage(), $failed);
            }

            return new PaymentVerificationData(paid: false, failureReason: $failed->getMessage());
        }

        return new PaymentVerificationData(
            paid: true,
            refId: $verified['ref_id'],
            cardPan: $verified['card_pan'],
            cardHash: $verified['card_hash'],
            feeType: $verified['fee_type'],
            fee: $verified['fee'],
        );
    }
}
