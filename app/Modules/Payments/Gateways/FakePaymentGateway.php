<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentLinkData;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Data\PaymentVerificationData;
use App\Modules\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Str;

/**
 * A stand-in bank for local and staging (roadmap S23) until Zarinpal (S36): its page offers «پرداخت موفق» and
 * «پرداخت ناموفق», which come back to the normal callback. Refuses to work unless payments.fake_enabled is on.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public const string NAME = 'fake';

    public function name(): string
    {
        return self::NAME;
    }

    public function request(PaymentRequestData $request): PaymentLinkData
    {
        if (! config('payments.fake_enabled')) {
            throw GatewayUnavailable::fakeNotAllowed();
        }

        $authority = 'FAKE'.Str::upper(Str::random(28));

        return new PaymentLinkData($authority, route('payments.fake', $authority));
    }

    public function verify(Payment $payment, array $query): PaymentVerificationData
    {
        if (($query['Status'] ?? null) === 'OK' && config('payments.fake_enabled')) {
            return new PaymentVerificationData(paid: true, refId: (string) random_int(10_000_000, 99_999_999), cardPan: '6037****1234');
        }

        return new PaymentVerificationData(paid: false, failureReason: 'پرداخت لغو شد یا ناموفق بود.');
    }
}
