{{-- «آدرس‌ها» (roadmap B9.9, D3.3): see App\Modules\Identity\Livewire\Account\Addresses. --}}
@use('App\Modules\Identity\Models\Address')
@use('App\Modules\Shared\Support\PersianDigits')

@php
    $fieldClass = 'h-11 w-full rounded-card border bg-white px-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700 disabled:bg-surface-50 disabled:text-ink-500';
    $field = function (string $key, string $label, array $attributes = []) use ($errors, $fieldClass): array {
        return ['key' => $key, 'label' => $label, 'attributes' => $attributes, 'class' => $fieldClass.' '.($errors->has('form.'.$key) ? 'border-danger-600' : 'border-line-200')];
    };
@endphp

<x-identity::account-shell active="addresses" title="آدرس‌ها">
    @if (! $canDeliver)
        <div class="rounded-card border border-warning-500 bg-surface-50 p-4 text-sm leading-7 text-ink-900" role="status">
            فعلاً ارسال به هیچ شهری فعال نیست، پس امکان افزودن آدرس وجود ندارد.
        </div>
    @endif

    @if ($this->addresses->isEmpty())
        <x-identity::empty-state icon="map-pin" message="هنوز آدرسی ثبت نکرده‌اید." hint="برای ثبت سفارش، دست‌کم یک آدرس لازم است." action-label="بازگشت به فروشگاه">
            @if ($canDeliver)
                <div class="mt-5">
                    <button type="button" wire:click="create" class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-5 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                        <x-ts-icon icon="plus" class="size-5" />
                        افزودن آدرس
                    </button>
                </div>
            @endif
        </x-identity::empty-state>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink-500">{{ PersianDigits::toPersian($this->addresses->count()) }} از {{ PersianDigits::toPersian($maxAddresses) }} آدرس</p>
            @if ($canDeliver && $this->addresses->count() < $maxAddresses)
                <button type="button" wire:click="create" class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-4 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                    <x-ts-icon icon="plus" class="size-5" />
                    افزودن آدرس
                </button>
            @endif
        </div>

        <ul class="grid gap-4 md:grid-cols-2">
            @foreach ($this->addresses as $address)
                <li wire:key="address-{{ $address->getId() }}" @class(['flex flex-col rounded-card border bg-white p-4', 'border-primary-700' => $address->isDefault(), 'border-line-200' => ! $address->isDefault()])>
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-bold text-ink-900">{{ $address->getTitle() }}</p>
                        @if ($address->isDefault())
                            <span class="rounded-full bg-primary-50 px-2 text-xs leading-6 font-bold text-primary-700">پیش‌فرض</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm leading-7 text-ink-900">
                        {{ $this->cityLabels[$address->getCityId()] ?? '' }}، {{ PersianDigits::toPersian($address->getFullAddress()) }}،
                        {{ PersianDigits::toPersian(implode('، ', array_filter([
                            'پلاک '.$address->getPlaque(),
                            $address->getUnit() ? 'واحد '.$address->getUnit() : null,
                            $address->getFloor() ? 'طبقه '.$address->getFloor() : null,
                        ]))) }}
                    </p>
                    <dl class="mt-2 grid gap-1 text-sm text-ink-500">
                        <div class="flex gap-1"><dt>گیرنده:</dt><dd class="text-ink-900">{{ $address->getReceiverName() }}</dd></div>
                        <div class="flex gap-1"><dt>موبایل:</dt><dd class="text-ink-900" dir="ltr">{{ PersianDigits::toPersian($address->getReceiverMobile()) }}</dd></div>
                        <div class="flex gap-1"><dt>کد پستی:</dt><dd class="text-ink-900" dir="ltr">{{ PersianDigits::toPersian($address->getPostalCode()) }}</dd></div>
                    </dl>
                    <div class="mt-auto flex flex-wrap gap-2 border-t border-line-200 pt-3">
                        <button type="button" wire:click="edit({{ $address->getId() }})" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                            <x-ts-icon icon="pencil-square" class="size-4" />
                            ویرایش
                        </button>
                        @unless ($address->isDefault())
                            <button type="button" wire:click="makeDefault({{ $address->getId() }})" class="inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                                <x-ts-icon icon="check-circle" class="size-4" />
                                پیش‌فرض شود
                            </button>
                        @endunless
                        <button type="button" wire:click="delete({{ $address->getId() }})" wire:confirm="آدرس «{{ $address->getTitle() }}» حذف شود؟" class="ms-auto inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-danger-600 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                            <x-ts-icon icon="trash" class="size-4" />
                            حذف
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <x-ts-modal wire="showForm" size="2xl" :title="$editingId === null ? 'افزودن آدرس' : 'ویرایش آدرس'">
        <form wire:submit="save" id="address-form" class="grid gap-4 md:grid-cols-2" novalidate>
            @foreach ([
                $field(Address::COLUMN_TITLE, 'عنوان آدرس', ['placeholder' => 'مثلاً خانه یا محل کار', 'maxlength' => 40]),
                $field(Address::COLUMN_RECEIVER_NAME, 'نام و نام خانوادگی گیرنده', ['autocomplete' => 'name', 'maxlength' => 80]),
                $field(Address::COLUMN_RECEIVER_MOBILE, 'موبایل گیرنده', ['type' => 'tel', 'inputmode' => 'numeric', 'dir' => 'ltr', 'autocomplete' => 'tel', 'placeholder' => '۰۹۱۲ ۳۴۵ ۶۷۸۹']),
            ] as $input)
                <div class="space-y-1.5 {{ $input['key'] === Address::COLUMN_TITLE ? 'md:col-span-2' : '' }}">
                    <label for="address-{{ $input['key'] }}" class="block text-sm font-medium text-ink-900">{{ $input['label'] }}</label>
                    <input id="address-{{ $input['key'] }}" wire:model="form.{{ $input['key'] }}" {{ new \Illuminate\View\ComponentAttributeBag($input['attributes']) }} class="{{ $input['class'] }}" @error('form.'.$input['key']) aria-invalid="true" aria-describedby="address-{{ $input['key'] }}-error" @enderror>
                    @error('form.'.$input['key']) <p id="address-{{ $input['key'] }}-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            @foreach ([
                [Address::COLUMN_PROVINCE_ID, 'استان', $provinces, 'انتخاب استان'],
                [Address::COLUMN_CITY_ID, 'شهر', $cities, filled($form[Address::COLUMN_PROVINCE_ID] ?? null) ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید'],
            ] as [$key, $label, $options, $placeholder])
                <div class="space-y-1.5">
                    <label for="address-{{ $key }}" class="block text-sm font-medium text-ink-900">{{ $label }}</label>
                    <select
                        id="address-{{ $key }}"
                        wire:model.live="form.{{ $key }}"
                        @disabled($options === [])
                        @class([$fieldClass, 'border-danger-600' => $errors->has('form.'.$key), 'border-line-200' => ! $errors->has('form.'.$key)])
                        @error('form.'.$key) aria-invalid="true" aria-describedby="address-{{ $key }}-error" @enderror
                    >
                        <option value="">{{ $placeholder }}</option>
                        @foreach ($options as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('form.'.$key) <p id="address-{{ $key }}-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="space-y-1.5 md:col-span-2">
                <label for="address-{{ Address::COLUMN_FULL_ADDRESS }}" class="block text-sm font-medium text-ink-900">نشانی</label>
                <textarea id="address-{{ Address::COLUMN_FULL_ADDRESS }}" wire:model="form.{{ Address::COLUMN_FULL_ADDRESS }}" rows="3" maxlength="500" placeholder="خیابان، کوچه و نشانی کامل" @class(['w-full rounded-card border bg-white p-3 text-sm leading-7 text-ink-900 focus:outline-2 focus:outline-primary-700', 'border-danger-600' => $errors->has('form.'.Address::COLUMN_FULL_ADDRESS), 'border-line-200' => ! $errors->has('form.'.Address::COLUMN_FULL_ADDRESS)]) @error('form.'.Address::COLUMN_FULL_ADDRESS) aria-invalid="true" aria-describedby="address-full-error" @enderror></textarea>
                @error('form.'.Address::COLUMN_FULL_ADDRESS) <p id="address-full-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-3 gap-3 md:col-span-2">
                @foreach ([
                    $field(Address::COLUMN_PLAQUE, 'پلاک', ['inputmode' => 'numeric', 'maxlength' => 10]),
                    $field(Address::COLUMN_UNIT, 'واحد', ['inputmode' => 'numeric', 'maxlength' => 10]),
                    $field(Address::COLUMN_FLOOR, 'طبقه', ['maxlength' => 10]),
                ] as $input)
                    <div class="space-y-1.5">
                        <label for="address-{{ $input['key'] }}" class="block text-sm font-medium text-ink-900">{{ $input['label'] }}</label>
                        <input id="address-{{ $input['key'] }}" wire:model="form.{{ $input['key'] }}" {{ new \Illuminate\View\ComponentAttributeBag($input['attributes']) }} class="{{ $input['class'] }}" @error('form.'.$input['key']) aria-invalid="true" aria-describedby="address-{{ $input['key'] }}-error" @enderror>
                        @error('form.'.$input['key']) <p id="address-{{ $input['key'] }}-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            @php($postal = $field(Address::COLUMN_POSTAL_CODE, 'کد پستی ۱۰ رقمی', ['inputmode' => 'numeric', 'dir' => 'ltr', 'maxlength' => 12, 'autocomplete' => 'postal-code']))
            <div class="space-y-1.5">
                <label for="address-{{ $postal['key'] }}" class="block text-sm font-medium text-ink-900">{{ $postal['label'] }}</label>
                <input id="address-{{ $postal['key'] }}" wire:model="form.{{ $postal['key'] }}" {{ new \Illuminate\View\ComponentAttributeBag($postal['attributes']) }} class="{{ $postal['class'] }}" @error('form.'.$postal['key']) aria-invalid="true" aria-describedby="address-postal-error" @enderror>
                @error('form.'.$postal['key']) <p id="address-postal-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex min-h-11 items-center gap-3 self-end text-sm text-ink-900">
                <input type="checkbox" wire:model="form.{{ Address::COLUMN_IS_DEFAULT }}" class="size-5 rounded border-line-200 text-primary-700 focus:ring-primary-700">
                آدرس پیش‌فرض
            </label>
        </form>

        <x-slot:footer>
            <div class="flex w-full gap-2">
                <button type="submit" form="address-form" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-card bg-primary-700 px-5 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:opacity-60 md:flex-none">
                    <x-ts-icon icon="arrow-path" class="size-4 animate-spin" wire:loading wire:target="save" />
                    ذخیره آدرس
                </button>
                <button type="button" wire:click="$set('showForm', false)" class="inline-flex min-h-11 items-center justify-center rounded-card border border-line-200 px-5 text-sm text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                    انصراف
                </button>
            </div>
        </x-slot:footer>
    </x-ts-modal>
</x-identity::account-shell>
