<?php

declare(strict_types=1);

namespace App\Modules\Orders\Livewire;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\TrackedOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * «پیگیری سفارش» at /track (roadmap S29): order number and mobile show the order's status, its history and the
 * carrier's tracking code, without signing in. Nothing else of the order is shown. A wrong number and a wrong mobile
 * get the same answer, and each visitor has 10 tries a minute.
 */
final class TrackOrder extends Component
{
    public const int MAX_TRIES_PER_MINUTE = 10;

    public string $number = '';

    public string $mobile = '';

    #[Locked]
    public ?int $foundOrderId = null;

    public function track(TrackedOrder $orders): void
    {
        $this->validate([
            'number' => ['required', 'string', 'max:30'],
            'mobile' => ['required', 'string', 'max:16'],
        ], attributes: ['number' => 'شماره سفارش', 'mobile' => 'موبایل']);

        $key = 'track-order:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_TRIES_PER_MINUTE)) {
            $this->foundOrderId = null;
            $this->addError('number', 'تلاش‌های شما زیاد بود؛ یک دقیقه دیگر دوباره امتحان کنید.');

            return;
        }

        RateLimiter::hit($key, 60);
        $this->foundOrderId = $orders->find($this->number, $this->mobile)?->getId();

        if ($this->foundOrderId === null) {
            $this->addError('number', 'سفارشی با این شماره و موبایل پیدا نشد.');
        }
    }

    public function render(): View
    {
        $order = $this->foundOrderId === null ? null : Order::query()->with('statusLogs')->find($this->foundOrderId);

        return view('orders::livewire.track-order', ['order' => $order])->title('پیگیری سفارش');
    }
}
