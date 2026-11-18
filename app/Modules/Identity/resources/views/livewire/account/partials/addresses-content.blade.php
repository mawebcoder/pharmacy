{{-- The address list and form, shared by «آدرس‌ها» and the checkout address step (S23). --}}
@use('App\Modules\Identity\Models\Address')
@use('App\Modules\Shared\Support\PersianDigits')
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
                @php($isSelected = $forCheckout ? $address->getId() === $selectedId : $address->isDefault())
                <li wire:key="address-{{ $address->getId() }}" @class(['flex flex-col rounded-card border bg-card p-4', 'border-2 border-primary-700' => $isSelected, 'border-line-200' => ! $isSelected])>
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-bold text-ink-900">{{ $address->getTitle() }}</p>
                        @if ($address->isDefault())
                            <span class="rounded-full bg-primary-50 px-2 text-xs leading-6 font-bold text-primary-700">پیش‌فرض</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm leading-7 text-ink-900">
                        {{ $address->text(province: $this->places[$address->getCityId()]['province'] ?? null, city: $this->places[$address->getCityId()]['city'] ?? null)->line() }}
                    </p>
                    <dl class="mt-2 grid gap-1 text-sm text-ink-500">
                        <div class="flex gap-1"><dt>گیرنده:</dt><dd class="text-ink-900">{{ $address->getReceiverName() }}</dd></div>
                        <div class="flex gap-1"><dt>موبایل:</dt><dd class="text-ink-900" dir="ltr">{{ PersianDigits::toPersian($address->getReceiverMobile()) }}</dd></div>
                        <div class="flex gap-1"><dt>کد پستی:</dt><dd class="text-ink-900" dir="ltr">{{ PersianDigits::toPersian($address->getPostalCode()) }}</dd></div>
                    </dl>
                    @if ($forCheckout)
                        <button
                            type="button"
                            wire:click="pick({{ $address->getId() }})"
                            @if ($isSelected) aria-pressed="true" @endif
                            @class([
                                'mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-card text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700',
                                'bg-primary-700 text-white hover:bg-primary-800' => ! $isSelected,
                                'bg-primary-50 text-primary-700' => $isSelected,
                            ])
                        >
                            <x-ts-icon :icon="$isSelected ? 'check-circle' : 'truck'" class="size-5" />
                            {{ $isSelected ? 'ارسال به این آدرس (انتخاب‌شده)' : 'ارسال به این آدرس' }}
                        </button>
                    @endif
                    <div class="mt-auto flex flex-wrap gap-2 pt-3">
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
                        <button type="button" x-data="{ working: false }" x-bind:disabled="working" x-bind:aria-busy="working" x-on:click="$store.confirm.ask({ title: 'حذف آدرس', message: @js('آدرس «'.$address->getTitle().'» حذف شود؟'), confirm: 'حذف' }).then((ok) => { if (! ok) return; working = true; $wire.delete({{ $address->getId() }}).finally(() => (working = false)); })" class="ms-auto inline-flex min-h-11 items-center gap-1 rounded-card px-3 text-sm text-danger-600 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700 disabled:cursor-wait disabled:opacity-60">
                            <x-ts-icon icon="trash" class="size-4" x-show="! working" />
                            <x-ui.spinner class="size-4" x-show="working" x-cloak />
                            حذف
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <x-ts-modal wire="showForm" handle size="2xl" :title="$editingId === null ? 'افزودن آدرس' : 'ویرایش آدرس'">
        {{-- After a save with errors, the first invalid field scrolls smoothly into view and gets the cursor (owner, 2026-11-15). --}}
        <form
            wire:submit="save"
            id="address-form"
            novalidate
            x-data="{
                submitted: false,
                forget: null,
                init() {
                    this.forget = Livewire.hook('morphed', ({ component }) => {
                        if (! this.submitted || component.id !== $wire.$id) return
                        this.submitted = false
                        setTimeout(() => this.showFirstError())
                    })
                },
                destroy() {
                    this.forget?.()
                },
                showFirstError() {
                    const field = this.$el.querySelector('[aria-invalid=true]')
                    if (! field) return
                    const smooth = ! window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    field.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'center' })
                    field.focus({ preventScroll: true })
                },
            }"
            x-on:submit="submitted = true"
            x-bind:aria-busy="submitted"
        >
            {{-- While saving, the fields are locked and softly blurred until the answer comes (owner, 2026-11-15). --}}
            <fieldset
                class="grid min-w-0 gap-4 transition-[opacity,filter] duration-200 ease-out motion-reduce:transition-none md:grid-cols-2"
                wire:loading.attr="disabled"
                wire:loading.class="pointer-events-none select-none opacity-60 blur-[1.5px]"
                wire:target="save"
            >
                @foreach ([
                    $field(Address::COLUMN_TITLE, 'عنوان آدرس', ['placeholder' => 'مثلاً خانه یا محل کار', 'maxlength' => 40]),
                    $field(Address::COLUMN_RECEIVER_NAME, 'نام و نام خانوادگی گیرنده', ['autocomplete' => 'name', 'maxlength' => 80, 'placeholder' => 'مثلاً مریم احمدی']),
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
                            @class([$fieldClass, 'border-danger-600' => $errors->has('form.'.$key), 'border-line-300' => ! $errors->has('form.'.$key)])
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

                <div class="grid gap-3 md:col-span-2 md:grid-cols-2">
                    @foreach ([
                        $field(Address::COLUMN_MAIN_STREET, 'خیابان اصلی', ['maxlength' => 120, 'placeholder' => 'مثلاً ولیعصر']),
                        $field(Address::COLUMN_SIDE_STREET, 'خیابان فرعی (اختیاری)', ['maxlength' => 120, 'placeholder' => 'مثلاً شهید رضایی']),
                        $field(Address::COLUMN_ALLEY, 'کوچه (اختیاری)', ['maxlength' => 120, 'placeholder' => 'مثلاً یاس']),
                        $field(Address::COLUMN_BLOCK, 'بلوک (اختیاری)', ['maxlength' => 20, 'placeholder' => 'مثلاً B']),
                    ] as $input)
                        <div class="space-y-1.5">
                            <label for="address-{{ $input['key'] }}" class="block text-sm font-medium text-ink-900">{{ $input['label'] }}</label>
                            <input id="address-{{ $input['key'] }}" wire:model="form.{{ $input['key'] }}" {{ new \Illuminate\View\ComponentAttributeBag($input['attributes']) }} class="{{ $input['class'] }}" @error('form.'.$input['key']) aria-invalid="true" aria-describedby="address-{{ $input['key'] }}-error" @enderror>
                            @error('form.'.$input['key']) <p id="address-{{ $input['key'] }}-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-3 gap-3 md:col-span-2">
                    @foreach ([
                        $field(Address::COLUMN_PLAQUE, 'پلاک', ['inputmode' => 'numeric', 'maxlength' => 10, 'placeholder' => 'مثلاً ۱۲']),
                        $field(Address::COLUMN_UNIT, 'واحد', ['inputmode' => 'numeric', 'maxlength' => 10, 'placeholder' => 'مثلاً ۴']),
                        $field(Address::COLUMN_FLOOR, 'طبقه', ['maxlength' => 10, 'placeholder' => 'مثلاً ۲ یا همکف']),
                    ] as $input)
                        <div class="space-y-1.5">
                            <label for="address-{{ $input['key'] }}" class="block text-sm font-medium text-ink-900">{{ $input['label'] }}</label>
                            <input id="address-{{ $input['key'] }}" wire:model="form.{{ $input['key'] }}" {{ new \Illuminate\View\ComponentAttributeBag($input['attributes']) }} class="{{ $input['class'] }}" @error('form.'.$input['key']) aria-invalid="true" aria-describedby="address-{{ $input['key'] }}-error" @enderror>
                            @error('form.'.$input['key']) <p id="address-{{ $input['key'] }}-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label for="address-{{ Address::COLUMN_FULL_ADDRESS }}" class="block text-sm font-medium text-ink-900">توضیحات تکمیلی (اختیاری)</label>
                    <textarea id="address-{{ Address::COLUMN_FULL_ADDRESS }}" wire:model="form.{{ Address::COLUMN_FULL_ADDRESS }}" rows="2" maxlength="500" placeholder="مثلاً روبه‌روی پارک، زنگ دوم" @class(['w-full rounded-card border bg-card p-3 text-sm leading-7 text-ink-900 focus:outline-2 focus:outline-primary-700', 'border-danger-600' => $errors->has('form.'.Address::COLUMN_FULL_ADDRESS), 'border-line-300' => ! $errors->has('form.'.Address::COLUMN_FULL_ADDRESS)]) @error('form.'.Address::COLUMN_FULL_ADDRESS) aria-invalid="true" aria-describedby="address-full-error" @enderror></textarea>
                    @error('form.'.Address::COLUMN_FULL_ADDRESS) <p id="address-full-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                @php($postal = $field(Address::COLUMN_POSTAL_CODE, 'کد پستی ۱۰ رقمی', ['inputmode' => 'numeric', 'dir' => 'ltr', 'maxlength' => 12, 'autocomplete' => 'postal-code', 'placeholder' => '۱۲۳۴۵۶۷۸۹۰']))
                <div class="space-y-1.5">
                    <label for="address-{{ $postal['key'] }}" class="block text-sm font-medium text-ink-900">{{ $postal['label'] }}</label>
                    <input id="address-{{ $postal['key'] }}" wire:model="form.{{ $postal['key'] }}" {{ new \Illuminate\View\ComponentAttributeBag($postal['attributes']) }} class="{{ $postal['class'] }}" @error('form.'.$postal['key']) aria-invalid="true" aria-describedby="address-postal-error" @enderror>
                    @error('form.'.$postal['key']) <p id="address-postal-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex min-h-11 items-center gap-3 self-end text-sm text-ink-900">
                    <input type="checkbox" wire:model="form.{{ Address::COLUMN_IS_DEFAULT }}" class="size-5 rounded border-line-300 text-primary-700 focus:ring-primary-700">
                    آدرس پیش‌فرض
                </label>
            </fieldset>
        </form>

        <x-slot:footer>
            <div class="flex w-full gap-2">
                <button type="submit" form="address-form" wire:loading.attr="disabled" wire:target="save" data-own-loading class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-card bg-primary-700 px-5 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:opacity-60 md:flex-none">
                    <x-ts-icon icon="arrow-path" class="size-4 animate-spin" wire:loading wire:target="save" />
                    ذخیره آدرس
                </button>
                <button type="button" wire:click="$set('showForm', false)" class="inline-flex min-h-11 items-center justify-center rounded-card border border-line-200 px-5 text-sm text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                    انصراف
                </button>
            </div>
        </x-slot:footer>
    </x-ts-modal>
