{{-- Checkout step 1 «آدرس ارسال» (roadmap B9.7): see App\Modules\Checkout\Livewire\AddressStep. --}}
<div class="mx-auto max-w-4xl space-y-4">
    {{-- Back to the cart sits on top (owner, 2026-11-15). --}}
    <a href="{{ route('cart') }}" wire:navigate class="-mb-2 inline-flex min-h-11 items-center gap-1 text-sm text-primary-700 hover:underline">
        <x-ts-icon icon="chevron-right" class="size-4" />
        بازگشت به سبد
    </a>

    <x-checkout::stepper current="address" />

    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-xl font-bold text-ink-900 xl:text-2xl">سفارش به کجا ارسال شود؟</h1>
            <p class="mt-1 text-sm text-ink-500">یکی از آدرس‌ها را برای این سفارش انتخاب کنید یا آدرس تازه‌ای اضافه کنید.</p>
        </div>
    </div>

    @livewire('identity::account.addresses', ['forCheckout' => true, 'selectedId' => $chosenId])

    @error('address')
        <p role="alert" class="text-sm text-danger-600">{{ $message }}</p>
    @enderror

    <div class="flex justify-end border-t border-line-200 pt-4">
        {{-- Phones: fixed above the bottom navigation, like the cart's «ادامه خرید»; disabled until an address is
             chosen (owner, 2026-11-15). --}}
        <div class="fixed inset-x-0 bottom-[calc(4rem+1px+env(safe-area-inset-bottom))] z-30 border-t border-line-200 bg-card p-3 md:static md:border-0 md:p-0">
            <button type="button" wire:click="proceed" @disabled(! $chosenId) class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-card bg-primary-700 px-6 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:bg-line-200 disabled:text-ink-500 md:w-auto">
                ادامه با آدرس انتخاب‌شده
                <x-ts-icon icon="chevron-left" class="size-4" />
            </button>
        </div>
    </div>

    {{-- Room for the phone's fixed bar. --}}
    <div class="h-20 md:hidden" aria-hidden="true"></div>
</div>
