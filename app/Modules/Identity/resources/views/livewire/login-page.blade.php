{{-- Sign in or sign up with an SMS code (roadmap B9.10): see App\Modules\Identity\Livewire\LoginPage. --}}
<div class="flex justify-center py-6 md:py-12">
    <div class="w-full max-w-[400px] rounded-card border border-line-200 bg-white p-6 md:p-8">
        <div class="flex flex-col items-center gap-3 text-center">
            <span class="grid size-12 place-items-center rounded-card bg-primary-700 text-white">
                <x-ts-icon icon="plus" class="size-6" />
            </span>
            <h1 class="text-xl font-bold text-ink-900">
                @if ($step === 'name')
                    خوش آمدید!
                @else
                    ورود یا ثبت‌نام
                @endif
            </h1>
        </div>

        @if ($step === 'mobile')
            <form wire:submit="sendCode" class="mt-6 space-y-4" novalidate>
                <p class="text-center text-sm text-ink-500">شماره موبایل خود را وارد کنید؛ کد تأیید برایتان پیامک می‌شود.</p>

                <div class="space-y-1.5">
                    <label for="login-mobile" class="block text-sm font-medium text-ink-900">شماره موبایل</label>
                    <input
                        id="login-mobile"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel"
                        dir="ltr"
                        maxlength="16"
                        placeholder="۰۹۱۲ ۳۴۵ ۶۷۸۹"
                        wire:model="mobile"
                        autofocus
                        @error('mobile') aria-invalid="true" aria-describedby="login-mobile-error" @enderror
                        @class([
                            'h-12 w-full rounded-card border bg-white px-4 text-center text-lg tracking-wider text-ink-900 placeholder:text-ink-500/60 focus:outline-2 focus:outline-primary-700',
                            'border-line-200' => ! $errors->has('mobile'),
                            'border-danger-600' => $errors->has('mobile'),
                        ])
                    >
                    @error('mobile')
                        <p id="login-mobile-error" class="flex items-center gap-1 text-sm text-danger-600">
                            <x-ts-icon icon="exclamation-circle" class="size-4 shrink-0" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="sendCode" class="flex h-12 w-full items-center justify-center gap-2 rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <x-ts-icon icon="arrow-path" class="size-5 animate-spin" wire:loading wire:target="sendCode" />
                    دریافت کد تأیید
                </button>

                <p class="text-center text-xs leading-6 text-ink-500">
                    ورود شما به معنای پذیرش <a href="/terms" class="text-primary-700 underline-offset-4 hover:underline">قوانین و مقررات</a> و
                    <a href="/privacy" class="text-primary-700 underline-offset-4 hover:underline">حریم خصوصی</a> {{ config('app.display_name') }} است.
                </p>
            </form>
        @elseif ($step === 'code')
            <form
                wire:submit="verify"
                class="mt-6 space-y-4"
                novalidate
                x-data="{
                    digits: ['', '', '', '', ''],
                    remaining: 0,
                    timer: null,
                    toLatin(value) {
                        return value.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\D/g, '');
                    },
                    toPersian(value) {
                        return String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
                    },
                    get countdown() {
                        const minutes = String(Math.floor(this.remaining / 60)).padStart(2, '0');
                        const seconds = String(this.remaining % 60).padStart(2, '0');
                        return this.toPersian(minutes + ':' + seconds);
                    },
                    tick() {
                        this.remaining = Math.max(0, $wire.resendAvailableAt - Math.floor(Date.now() / 1000));
                    },
                    startTimer() {
                        clearInterval(this.timer);
                        this.tick();
                        this.timer = setInterval(() => this.tick(), 1000);
                    },
                    boxes() {
                        return [...this.$refs.pin.querySelectorAll('input')];
                    },
                    fill(from, value) {
                        const chars = this.toLatin(value).split('');
                        let index = from;
                        for (const char of chars) {
                            if (index > 4) break;
                            this.digits[index++] = char;
                        }
                        if (chars.length === 0) this.digits[from] = '';
                        this.$nextTick(() => this.boxes()[Math.min(index, 4)].focus());
                        this.submitWhenFull();
                    },
                    back(index) {
                        if (this.digits[index] === '' && index > 0) {
                            this.digits[index - 1] = '';
                            this.boxes()[index - 1].focus();
                        }
                    },
                    submitWhenFull() {
                        const code = this.digits.join('');
                        if (code.length === 5) {
                            $wire.code = code;
                            $wire.verify();
                        }
                    },
                    clear() {
                        this.digits = ['', '', '', '', ''];
                        this.$nextTick(() => this.boxes()[0].focus());
                    },
                }"
                x-init="startTimer(); $nextTick(() => boxes()[0].focus())"
                x-on:otp-rejected.window="clear()"
                x-on:otp-resent.window="clear(); startTimer()"
            >
                @if ($notice)
                    <p role="status" class="flex items-start gap-2 rounded-card bg-primary-50 px-3 py-2.5 text-sm leading-6 text-primary-800">
                        <x-ts-icon icon="information-circle" class="mt-0.5 size-5 shrink-0" />
                        {{ $notice }}
                    </p>
                @endif

                @if ($bypassCode)
                    <p class="rounded-card border border-dashed border-warning-500 px-3 py-2 text-center text-xs text-ink-900">
                        محیط توسعه: کد <span dir="ltr" class="font-bold">{{ \App\Modules\Shared\Support\PersianDigits::toPersian($bypassCode) }}</span> هم پذیرفته می‌شود.
                    </p>
                @endif

                <p class="text-center text-sm text-ink-500">
                    کد ۵ رقمی پیامک‌شده به <span dir="ltr" class="font-medium text-ink-900">{{ \App\Modules\Shared\Support\PersianDigits::toPersian($verifiedMobile) }}</span> را وارد کنید.
                </p>

                <fieldset class="space-y-2">
                    <legend class="sr-only">کد تأیید</legend>
                    <div x-ref="pin" dir="ltr" class="flex justify-center gap-2">
                        @foreach (range(0, 4) as $index)
                            <input
                                type="text"
                                inputmode="numeric"
                                maxlength="5"
                                aria-label="رقم {{ \App\Modules\Shared\Support\PersianDigits::toPersian($index + 1) }} کد"
                                @if ($index === 0) autocomplete="one-time-code" @else autocomplete="off" @endif
                                x-bind:value="toPersian(digits[{{ $index }}])"
                                x-on:input="fill({{ $index }}, $event.target.value); $event.target.value = toPersian(digits[{{ $index }}])"
                                x-on:keydown.backspace="back({{ $index }})"
                                x-on:paste.prevent="fill(0, $event.clipboardData.getData('text'))"
                                x-on:focus="$event.target.select()"
                                wire:loading.attr="disabled"
                                wire:target="verify"
                                @class([
                                    'size-12 rounded-card border bg-white text-center text-xl font-bold text-ink-900 focus:outline-2 focus:outline-primary-700 disabled:opacity-60 md:size-14',
                                    'border-line-200' => ! $errors->has('code'),
                                    'border-danger-600' => $errors->has('code'),
                                ])
                            >
                        @endforeach
                    </div>
                    @error('code')
                        <p role="alert" class="flex items-center justify-center gap-1 text-sm text-danger-600">
                            <x-ts-icon icon="exclamation-circle" class="size-4 shrink-0" />
                            {{ $message }}
                        </p>
                    @enderror
                </fieldset>

                <button type="submit" wire:loading.attr="disabled" wire:target="verify" x-bind:disabled="digits.join('').length !== 5" class="flex h-12 w-full items-center justify-center gap-2 rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <x-ts-icon icon="arrow-path" class="size-5 animate-spin" wire:loading wire:target="verify" />
                    ورود
                </button>

                <div class="flex items-center justify-between text-sm">
                    <button type="button" wire:click="editMobile" class="flex min-h-11 items-center gap-1 text-primary-700 hover:underline">
                        <x-ts-icon icon="pencil-square" class="size-4" />
                        ویرایش شماره
                    </button>

                    <span x-show="remaining > 0" class="text-ink-500">
                        ارسال مجدد کد <span x-text="countdown" dir="ltr" class="inline-block tabular-nums"></span>
                    </span>
                    <button
                        type="button"
                        x-show="remaining === 0"
                        x-cloak
                        wire:click="resend"
                        wire:loading.attr="disabled"
                        wire:target="resend"
                        class="min-h-11 font-medium text-primary-700 hover:underline disabled:opacity-60"
                    >
                        ارسال مجدد کد
                    </button>
                </div>
            </form>
        @else
            <form wire:submit="saveName" class="mt-6 space-y-4" novalidate>
                <p class="text-center text-sm text-ink-500">نام خود را بنویسید تا سفارش‌ها و پیامک‌ها با نام شما ارسال شوند.</p>

                <x-ts-input id="login-first-name" label="نام" wire:model="firstName" autocomplete="given-name" maxlength="60" />
                <x-ts-input id="login-last-name" label="نام خانوادگی" wire:model="lastName" autocomplete="family-name" maxlength="60" />

                <div class="flex gap-3">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveName" class="flex h-12 flex-1 items-center justify-center gap-2 rounded-card bg-primary-700 font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <x-ts-icon icon="arrow-path" class="size-5 animate-spin" wire:loading wire:target="saveName" />
                    ذخیره
                </button>
                    <button type="button" wire:click="finish" class="h-12 rounded-card px-5 font-medium text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700">بعداً</button>
                </div>
            </form>
        @endif
    </div>
</div>
