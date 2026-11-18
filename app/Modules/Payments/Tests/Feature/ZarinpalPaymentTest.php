<?php

declare(strict_types=1);

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Actions\HandlePaymentCallback;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Events\PaymentSucceeded;
use App\Modules\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Payments\Gateways\Zarinpal\ZarinpalClient;
use App\Modules\Payments\Jobs\ReconcileZarinpalPayments;
use App\Modules\Payments\Models\Payment;
use App\Modules\System\Models\IntegrationLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/*
| Zarinpal through its SDK (roadmap D7, S36): request, the customer's return, verify and reconciliation, all against
| Http::fake (answers shaped as in Zarinpal's API v4 docs and its sandbox).
*/

const TEST_MERCHANT = '00000000-1111-2222-3333-444444444444';

beforeEach(function (): void {
    config([
        'payments.gateway' => 'zarinpal',
        'payments.zarinpal.merchant_id' => TEST_MERCHANT,
        'payments.zarinpal.sandbox' => true,
        'payments.zarinpal.sandbox_base_url' => 'https://sandbox.zarinpal.test',
        'payments.zarinpal.callback_url' => 'https://hutana.test/payment/callback',
    ]);
});

/**
 * @param  array<string, mixed>  $data
 */
function zarinpalOk(array $data): array
{
    return ['data' => $data, 'errors' => []];
}

function zarinpalError(int $code, string $message = 'error'): array
{
    return ['data' => [], 'errors' => ['code' => $code, 'message' => $message, 'validations' => []]];
}

function pendingZarinpalPayment(string $authority, int $amount = 2_300_000): Payment
{
    $order = Order::factory()->create([Order::COLUMN_TOTAL => $amount]);
    $payment = (new Payment)
        ->setOrderId($order->getId())
        ->setGateway('zarinpal')
        ->setAmount($amount)
        ->setAuthority($authority)
        ->setUrl('https://sandbox.zarinpal.test/pg/StartPay/'.$authority)
        ->setStatus(PaymentAttemptStatus::Pending)
        ->setExpiresAt(now()->addMinutes(30));
    $payment->save();

    return $payment;
}

describe('opening a payment', function (): void {
    it('sends the amount in rial with the callback and sends the customer to StartPay', function (): void {
        Http::fake(['sandbox.zarinpal.test/pg/v4/payment/request.json' => Http::response(zarinpalOk(['code' => 100, 'message' => 'Success', 'authority' => 'S0000000000000000000000000000000abcd', 'fee_type' => 'Merchant', 'fee' => 2300]))]);

        $link = app(PaymentGateway::class)->request(new PaymentRequestData(7, 2_300_000, 'سفارش 140508-000007', '09123456789', now()->addMinutes(30)));

        expect($link->authority)->toBe('S0000000000000000000000000000000abcd')
            ->and($link->url)->toBe('https://sandbox.zarinpal.test/pg/StartPay/S0000000000000000000000000000000abcd');
        Http::assertSent(fn (Request $request): bool => $request['merchant_id'] === TEST_MERCHANT
            && $request['amount'] === 2_300_000
            && $request['currency'] === 'IRR'
            && $request['callback_url'] === 'https://hutana.test/payment/callback'
            && $request['metadata']['mobile'] === '09123456789');
    });

    it('says the gateway is unavailable, with Zarinpal\'s reason, when the request is refused', function (): void {
        Http::fake(['sandbox.zarinpal.test/*' => Http::response(zarinpalError(-14, 'callback domain mismatch'))]);

        expect(fn () => app(PaymentGateway::class)->request(new PaymentRequestData(7, 2_300_000, 'سفارش', '', now()->addMinutes(30))))
            ->toThrow(GatewayUnavailable::class, 'آدرس بازگشت با دامنه ثبت‌شده در زرین‌پال یکی نیست.');
    });

    it('logs the call without the merchant id', function (): void {
        Http::fake(['sandbox.zarinpal.test/*' => Http::response(zarinpalOk(['code' => 100, 'message' => 'Success', 'authority' => 'S0000000000000000000000000000000abcd', 'fee_type' => 'Merchant', 'fee' => 0]))]);

        app(PaymentGateway::class)->request(new PaymentRequestData(7, 2_300_000, 'سفارش', '', now()->addMinutes(30)));

        $log = IntegrationLog::query()->where(IntegrationLog::COLUMN_SERVICE, 'zarinpal')->sole();
        expect($log->getOperation())->toBe('request')
            ->and(json_encode($log->getRequest()))->not->toContain(TEST_MERCHANT);
    });
});

describe('the customer coming back', function (): void {
    it('marks the payment failed without asking Zarinpal when it says NOK', function (): void {
        Http::fake();
        $payment = pendingZarinpalPayment('S0000000000000000000000000000000nok1');

        $_ = app(HandlePaymentCallback::class)(['Authority' => $payment->getAuthority(), 'Status' => 'NOK']);

        expect($payment->refresh()->getStatus())->toBe(PaymentAttemptStatus::Failed)
            ->and($payment->getFailureReason())->toBe('پرداخت لغو شد یا ناموفق بود.');
        Http::assertNothingSent();
    });

    it('verifies with the payment\'s own amount and keeps Zarinpal\'s reference, card and fee', function (int $code): void {
        Event::fake([PaymentSucceeded::class]);
        Http::fake(['sandbox.zarinpal.test/pg/v4/payment/verify.json' => Http::response(zarinpalOk(['code' => $code, 'message' => 'Verified', 'ref_id' => 201, 'card_pan' => '502229******5995', 'card_hash' => '1EBE3EBEBE35C7EC0F8D6EE4F2F859107A87822CA179BC9528767EA7B5489B69', 'fee_type' => 'Merchant', 'fee' => 2300]))]);
        $payment = pendingZarinpalPayment('S0000000000000000000000000000000ok01');

        $_ = app(HandlePaymentCallback::class)(['Authority' => $payment->getAuthority(), 'Status' => 'OK', 'Amount' => '1']);

        expect($payment->refresh())
            ->getStatus()->toBe(PaymentAttemptStatus::Paid)
            ->getRefId()->toBe('201')
            ->getCardPan()->toBe('502229******5995')
            ->getFeeType()->toBe('Merchant')
            ->getFee()->toBe(2300)
            ->and($payment->getCardHash())->toStartWith('1EBE3E');
        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
        Http::assertSent(fn (Request $request): bool => $request['amount'] === 2_300_000 && $request['authority'] === 'S0000000000000000000000000000000ok01');
    })->with(['verified now' => [100], 'already verified' => [101]]);

    it('fails the payment with Zarinpal\'s reason when verify refuses it', function (int $code, string $reason): void {
        Event::fake([PaymentSucceeded::class]);
        Http::fake(['sandbox.zarinpal.test/*' => Http::response(zarinpalError($code))]);
        $payment = pendingZarinpalPayment('S0000000000000000000000000000000bad1');

        $_ = app(HandlePaymentCallback::class)(['Authority' => $payment->getAuthority(), 'Status' => 'OK']);

        expect($payment->refresh()->getStatus())->toBe(PaymentAttemptStatus::Failed)
            ->and($payment->getFailureReason())->toBe($reason);
        Event::assertNotDispatched(PaymentSucceeded::class);
    })->with([
        'amount mismatch' => [-50, 'مبلغ پرداخت با مبلغ سفارش یکی نیست.'],
        'payment failed' => [-51, 'پرداخت ناموفق بود.'],
    ]);

    it('leaves the payment pending when Zarinpal cannot be reached, for reconciliation to settle', function (): void {
        Http::fake(['sandbox.zarinpal.test/*' => Http::response('Bad Gateway', 502)]);
        $payment = pendingZarinpalPayment('S0000000000000000000000000000000down');

        $orderId = app(HandlePaymentCallback::class)(['Authority' => $payment->getAuthority(), 'Status' => 'OK']);

        expect($orderId)->toBe($payment->getOrderId())
            ->and($payment->refresh()->getStatus())->toBe(PaymentAttemptStatus::Pending);
    });
});

describe('reconciliation', function (): void {
    it('verifies our pending payments Zarinpal took but nobody verified, and leaves the rest alone', function (): void {
        Event::fake([PaymentSucceeded::class]);
        $ours = pendingZarinpalPayment('S0000000000000000000000000000000ours');
        $settled = pendingZarinpalPayment('S0000000000000000000000000000000done');
        $settled->setStatus(PaymentAttemptStatus::Failed)->save();
        Http::fake([
            'sandbox.zarinpal.test/pg/v4/payment/unVerified.json' => Http::response(zarinpalOk(['code' => 100, 'message' => 'Success', 'authorities' => [
                ['authority' => $ours->getAuthority(), 'amount' => 2_300_000, 'callback_url' => 'https://hutana.test/payment/callback', 'referer' => '', 'date' => '2026-11-18 10:00:00'],
                ['authority' => $settled->getAuthority(), 'amount' => 2_300_000, 'callback_url' => '', 'referer' => '', 'date' => ''],
                ['authority' => 'S0000000000000000000000000000notours', 'amount' => 10_000, 'callback_url' => '', 'referer' => '', 'date' => ''],
            ]])),
            'sandbox.zarinpal.test/pg/v4/payment/verify.json' => Http::response(zarinpalOk(['code' => 100, 'message' => 'Verified', 'ref_id' => 202, 'card_pan' => '502229******5995', 'card_hash' => 'AB', 'fee_type' => 'Merchant', 'fee' => 0])),
        ]);

        (new ReconcileZarinpalPayments)->handle(app(ZarinpalClient::class), app(HandlePaymentCallback::class));

        expect($ours->refresh()->getStatus())->toBe(PaymentAttemptStatus::Paid)
            ->and($settled->refresh()->getStatus())->toBe(PaymentAttemptStatus::Failed);
        Http::assertSentCount(2); // the list, and one verify for our pending payment
        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    });

    it('does nothing when another gateway is in use', function (): void {
        config(['payments.gateway' => 'fake']);
        Http::fake();

        (new ReconcileZarinpalPayments)->handle(app(ZarinpalClient::class), app(HandlePaymentCallback::class));

        Http::assertNothingSent();
    });
});
