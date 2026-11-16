{{-- «پیگیری سفارش» (roadmap S29): see App\Modules\Orders\Livewire\TrackOrder. Only the status, its history and the tracking code. --}}
@use('App\Modules\Shared\Support\JalaliDate')

@php
    $input = 'h-12 w-full rounded-card border bg-card px-4 text-ink-900 placeholder:text-ink-500/60 focus:outline-2 focus:outline-primary-700';
@endphp

<div class="flex justify-center py-6 md:py-12">
    <div class="w-full max-w-lg space-y-4">
        <div class="rounded-card border border-line-200 bg-card p-6 md:p-8">
            <div class="flex flex-col items-center gap-3 text-center">
                <span class="grid size-12 place-items-center rounded-card bg-primary-50 text-primary-700">
                    <x-ts-icon icon="truck" class="size-6" />
                </span>
                <h1 class="text-xl font-bold text-ink-900">پیگیری سفارش</h1>
                <p class="text-sm text-ink-500">شماره سفارش و موبایلی را که با آن سفارش داده‌اید (یا موبایل گیرنده) وارد کنید.</p>
            </div>

            <form wire:submit="track" class="mt-6 space-y-4" novalidate>
                <div class="space-y-1.5">
                    <label for="track-number" class="block text-sm font-medium text-ink-900">شماره سفارش</label>
                    <input id="track-number" wire:model="number" dir="ltr" maxlength="30" autocomplete="off" placeholder="PH-140508-000123"
                        @error('number') aria-invalid="true" aria-describedby="track-number-error" @enderror
                        @class([$input, 'border-line-300' => ! $errors->has('number'), 'border-danger-600' => $errors->has('number')])>
                    @error('number')
                        <p id="track-number-error" class="flex items-center gap-1 text-sm text-danger-600" role="alert">
                            <x-ts-icon icon="exclamation-circle" class="size-4 shrink-0" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>
                <div class="space-y-1.5">
                    <label for="track-mobile" class="block text-sm font-medium text-ink-900">موبایل</label>
                    <input id="track-mobile" wire:model="mobile" type="tel" inputmode="numeric" autocomplete="tel" dir="ltr" maxlength="16" placeholder="۰۹۱۲ ۳۴۵ ۶۷۸۹"
                        @error('mobile') aria-invalid="true" aria-describedby="track-mobile-error" @enderror
                        @class([$input, 'border-line-300' => ! $errors->has('mobile'), 'border-danger-600' => $errors->has('mobile')])>
                    @error('mobile')
                        <p id="track-mobile-error" class="flex items-center gap-1 text-sm text-danger-600">
                            <x-ts-icon icon="exclamation-circle" class="size-4 shrink-0" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>
                <button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                    پیگیری
                </button>
            </form>
        </div>

        @if ($order)
            <section aria-labelledby="tracked-order" class="rounded-card border border-line-200 bg-card p-6" wire:key="tracked-{{ $order->getId() }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="tracked-order" class="font-bold text-ink-900" dir="ltr">{{ $order->getNumber() }}</h2>
                    <x-orders::status-badge :status="$order->getStatus()" />
                </div>
                <p class="mt-1 text-sm text-ink-500">{{ $order->getPaidAt() ? 'پرداخت در' : 'ثبت در' }} {{ JalaliDate::display($order->getOrderDate(), 'j F Y') }}</p>

                @if ($order->getTrackingCode())
                    <p class="mt-3 rounded-md bg-primary-50 px-3 py-2 text-sm text-primary-800">
                        کد رهگیری مرسوله:
                        <span class="font-bold select-all" dir="ltr">{{ $order->getTrackingCode() }}</span>
                    </p>
                @endif

                <ol class="mt-4 space-y-3">
                    @foreach ($order->statusLogs as $log)
                        <li class="flex gap-3">
                            <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-primary-700' => $loop->last, 'bg-line-300' => ! $loop->last])></span>
                            <div>
                                <p @class(['text-sm text-ink-900', 'font-bold' => $loop->last])>{{ $log->getToStatus()->label() }}</p>
                                @if ($log->getCreatedAt())
                                    <p class="text-xs text-ink-500">{{ JalaliDate::display($log->getCreatedAt(), 'j F Y، H:i') }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</div>
