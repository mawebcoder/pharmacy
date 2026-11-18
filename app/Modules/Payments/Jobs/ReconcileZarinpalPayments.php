<?php

declare(strict_types=1);

namespace App\Modules\Payments\Jobs;

use App\Modules\Payments\Actions\HandlePaymentCallback;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Exceptions\ZarinpalRequestFailed;
use App\Modules\Payments\Gateways\Zarinpal\ZarinpalClient;
use App\Modules\Payments\Gateways\Zarinpal\ZarinpalGateway;
use App\Modules\Payments\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Settles payments Zarinpal took but nobody verified (roadmap D7.2 step 6, S36): the customer paid and closed the
 * browser before coming back. Zarinpal's unverified list is matched to our pending Zarinpal payments, and each match
 * goes through HandlePaymentCallback as an «OK» return, so locking, the one-paid-payment rule and OrderPaid all hold.
 * Authorities that are not ours, or no longer pending, are left alone (Zarinpal returns that money by itself).
 */
final class ReconcileZarinpalPayments implements ShouldQueue
{
    use Queueable;

    public function handle(ZarinpalClient $client, HandlePaymentCallback $handleCallback): void
    {
        if (config('payments.gateway') !== ZarinpalGateway::NAME) {
            return;
        }

        try {
            $unverified = $client->unverified();
        } catch (ZarinpalRequestFailed $failed) {
            Log::warning('Zarinpal reconciliation skipped.', ['reason' => $failed->getMessage()]);

            return;
        }

        if ($unverified === []) {
            return;
        }

        $pending = Payment::query()
            ->where(Payment::COLUMN_GATEWAY, ZarinpalGateway::NAME)
            ->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Pending)
            ->whereIn(Payment::COLUMN_AUTHORITY, array_column($unverified, 'authority'))
            ->pluck(Payment::COLUMN_AUTHORITY);

        foreach ($pending as $authority) {
            $_ = $handleCallback(['Authority' => $authority, 'Status' => 'OK']);
        }
    }
}
