<?php

use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Models\Payment;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The fake gateway's «bank» page (S25, local and staging only, payments.fake_enabled): the amount and two buttons
 * that return to the normal callback, as a real bank would. See FakePaymentGateway.
 */
new #[Title('درگاه آزمایشی')] class extends Component
{
    public Payment $payment;

    public function mount(string $authority): void
    {
        abort_unless((bool) config('payments.fake_enabled'), 404);

        $this->payment = Payment::query()
            ->where(Payment::COLUMN_AUTHORITY, $authority)
            ->where(Payment::COLUMN_STATUS, PaymentAttemptStatus::Pending)
            ->firstOrFail();
    }
};
?>

<div class="mx-auto max-w-md space-y-4 rounded-card border border-dashed border-warning-500 bg-card p-6 text-center">
    <p class="text-sm font-bold text-ink-900">درگاه پرداخت آزمایشی؛ پولی جابه‌جا نمی‌شود.</p>
    <p class="text-ink-500">مبلغ قابل پرداخت</p>
    <p class="text-2xl font-bold text-ink-900">@toman($payment->getAmount())</p>
    <div class="grid gap-3 pt-2">
        <a href="{{ route('payments.callback', ['Authority' => $payment->getAuthority(), 'Status' => 'OK']) }}" class="flex h-12 items-center justify-center rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800">پرداخت موفق</a>
        <a href="{{ route('payments.callback', ['Authority' => $payment->getAuthority(), 'Status' => 'NOK']) }}" class="flex h-12 items-center justify-center rounded-card border border-line-200 text-ink-900 hover:bg-surface-50">پرداخت ناموفق</a>
    </div>
</div>
