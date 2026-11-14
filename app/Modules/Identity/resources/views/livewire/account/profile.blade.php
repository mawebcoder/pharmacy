{{-- «اطلاعات حساب» (roadmap B9.9): see App\Modules\Identity\Livewire\Account\Profile. --}}
@use('App\Modules\Identity\Models\Customer')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $inputClass = fn (string $key): string => 'h-11 w-full rounded-card border bg-white px-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700 '.($errors->has('form.'.$key) ? 'border-danger-600' : 'border-line-200');
    $text = [
        [Customer::COLUMN_FIRST_NAME, 'نام', ['autocomplete' => 'given-name', 'maxlength' => 60]],
        [Customer::COLUMN_LAST_NAME, 'نام خانوادگی', ['autocomplete' => 'family-name', 'maxlength' => 60]],
        [Customer::COLUMN_NATIONAL_ID, 'کد ملی', ['inputmode' => 'numeric', 'dir' => 'ltr', 'maxlength' => 12]],
        [Customer::COLUMN_EMAIL, 'ایمیل (اختیاری)', ['type' => 'email', 'dir' => 'ltr', 'autocomplete' => 'email', 'maxlength' => 120]],
    ];
@endphp

<x-identity::account-shell active="profile" title="اطلاعات حساب">
    <form wire:submit="save" class="space-y-5 rounded-card border border-line-200 bg-white p-4 md:p-6" novalidate>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($text as [$key, $label, $attributes])
                <div class="space-y-1.5">
                    <label for="profile-{{ $key }}" class="block text-sm font-medium text-ink-900">{{ $label }}</label>
                    <input id="profile-{{ $key }}" wire:model="form.{{ $key }}" {{ new \Illuminate\View\ComponentAttributeBag($attributes) }} class="{{ $inputClass($key) }}" @error('form.'.$key) aria-invalid="true" aria-describedby="profile-{{ $key }}-error" @enderror>
                    @error('form.'.$key) <p id="profile-{{ $key }}-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="space-y-1.5">
                <label for="profile-mobile" class="block text-sm font-medium text-ink-900">شماره موبایل</label>
                <input id="profile-mobile" value="{{ PersianDigits::toPersian($mobile) }}" dir="ltr" disabled class="h-11 w-full rounded-card border border-line-200 bg-surface-50 px-3 text-sm text-ink-500">
                <p class="text-xs text-ink-500">شماره موبایل برای ورود است و اینجا تغییر نمی‌کند.</p>
            </div>

            <x-jalali-date label="تاریخ تولد (اختیاری)" wire:model="form.{{ Customer::COLUMN_BIRTH_DATE }}" :max="now()->subDay()" />
        </div>

        <fieldset class="space-y-2">
            <legend class="text-sm font-medium text-ink-900">جنسیت (اختیاری)</legend>
            <div class="flex flex-wrap gap-4">
                @foreach ($genders as $value => $label)
                    <label class="flex min-h-11 items-center gap-2 text-sm text-ink-900">
                        <input type="radio" name="gender" value="{{ $value }}" wire:model="form.{{ Customer::COLUMN_GENDER }}" class="size-5 border-line-200 text-primary-700 focus:ring-primary-700">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('form.'.Customer::COLUMN_GENDER) <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
        </fieldset>

        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-card bg-primary-700 px-6 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:opacity-60">
            <x-ts-icon icon="arrow-path" class="size-4 animate-spin" wire:loading wire:target="save" />
            ذخیره تغییرات
        </button>
    </form>
</x-identity::account-shell>
