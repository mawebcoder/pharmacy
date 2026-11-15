{{-- Toast body after an add when the order is close to free shipping (owner, 2026-11-14, S19). Variables: $summary, $previousProgress (the bar grows from it). --}}
@use('App\Modules\Shared\Support\Toman')

<span class="block text-sm">{{ Toman::format($summary->remainingForFreeShipping()) }} تا ارسال رایگان</span>
<span class="mt-2 block h-2 overflow-hidden rounded-full bg-surface-50" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $summary->freeShippingProgress() }}" aria-label="پیشرفت تا ارسال رایگان">
    <span class="block h-full rounded-full bg-primary-700 animate-[progress-grow_900ms_cubic-bezier(0.22,1,0.36,1)_200ms_both]" style="--progress-from: {{ $previousProgress }}%; width: {{ $summary->freeShippingProgress() }}%"></span>
</span>
