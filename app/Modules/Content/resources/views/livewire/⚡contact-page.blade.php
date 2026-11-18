<?php

use App\Modules\Content\Actions\SendContactMessage;
use App\Modules\Content\Data\ContactMessageData;
use App\Modules\Content\Enums\ContactSubject;
use App\Modules\Content\Exceptions\ContactMessageRefused;
use App\Modules\Content\Http\Requests\SendContactMessageRequest;
use App\Modules\Content\Models\ContactMessage;
use App\Modules\Identity\Queries\CustomerContact;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «تماس با ما» (roadmap B5 /contact, S29): name, mobile, optional email, a subject and the message, saved for staff
 * in «پیام‌های تماس». At most 3 messages every 10 minutes per IP and per mobile (SendContactMessage). A signed-in
 * customer's name and mobile are filled in. Beside the form: phone, email, chat links, working hours and address from
 * the store settings (S30), order tracking and the FAQ.
 */
new class extends Component
{
    /** @var array<string, string> */
    public array $form = [
        ContactMessage::COLUMN_NAME => '',
        ContactMessage::COLUMN_MOBILE => '',
        ContactMessage::COLUMN_EMAIL => '',
        ContactMessage::COLUMN_SUBJECT => '',
        ContactMessage::COLUMN_BODY => '',
    ];

    public bool $sent = false;

    public function mount(CustomerContact $contacts): void
    {
        $customerId = auth('customer')->id();
        $contact = $customerId === null ? null : $contacts->for((int) $customerId);

        if ($contact !== null) {
            $this->form[ContactMessage::COLUMN_NAME] = $contact['name'] ?? '';
            $this->form[ContactMessage::COLUMN_MOBILE] = $contact['mobile'];
        }
    }

    public function send(SendContactMessage $sendMessage): void
    {
        $rules = [];

        foreach (SendContactMessageRequest::baseRules() as $field => $fieldRules) {
            $rules['form.'.$field] = $fieldRules;
        }

        $attributes = [];

        foreach (SendContactMessageRequest::fieldLabels() as $field => $label) {
            $attributes['form.'.$field] = $label;
        }

        $validated = $this->validate($rules, attributes: $attributes)['form'];
        $customerId = auth('customer')->id();

        try {
            $_ = $sendMessage(ContactMessageData::fromArray($validated, $customerId === null ? null : (int) $customerId, request()->ip()));
        } catch (ContactMessageRefused $refused) {
            $this->addError('form.'.ContactMessage::COLUMN_BODY, $refused->getMessage());

            return;
        }

        $this->sent = true;
        $this->form[ContactMessage::COLUMN_SUBJECT] = '';
        $this->form[ContactMessage::COLUMN_BODY] = '';
    }

    public function writeAnother(): void
    {
        $this->sent = false;
    }

    public function render(StoreSettings $settings): View
    {
        return $this->view([
            'subjects' => ContactSubject::options(),
            'whatsAppUrl' => $settings->whatsAppChatUrl($settings->generalChatMessage()),
            'telegramUrl' => $settings->telegramChatUrl($settings->generalChatMessage()),
            'phone' => $settings->phone(),
            'phoneUrl' => $settings->phoneUrl(),
            'email' => $settings->email(),
            'address' => $settings->address(),
            'workingHours' => $settings->workingHours(),
        ])
            ->title('تماس با ما')
            ->layoutData(['description' => 'پرسش، پیگیری سفارش یا پیشنهاد خود را برای ما بفرستید.']);
    }
};
?>

@php
    $inputClass = fn (string $key): string => 'w-full rounded-card border bg-card px-3 text-sm text-ink-900 focus:outline-2 focus:outline-primary-700 '.($errors->has('form.'.$key) ? 'border-danger-600' : 'border-line-300');
    $fields = [
        [ContactMessage::COLUMN_NAME, 'نام و نام خانوادگی', ['autocomplete' => 'name', 'maxlength' => 80]],
        [ContactMessage::COLUMN_MOBILE, 'شماره موبایل', ['type' => 'tel', 'inputmode' => 'tel', 'dir' => 'ltr', 'autocomplete' => 'tel', 'maxlength' => 20, 'placeholder' => '09123456789']],
        [ContactMessage::COLUMN_EMAIL, 'ایمیل (اختیاری)', ['type' => 'email', 'dir' => 'ltr', 'autocomplete' => 'email', 'maxlength' => 120]],
    ];
@endphp

<div class="mx-auto max-w-5xl">
    <nav aria-label="مسیر صفحه" class="mb-4 text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700 hover:underline">خانه</a></li>
            <li aria-hidden="true"><x-ts-icon icon="chevron-left" class="size-3" /></li>
            <li aria-current="page" class="text-ink-900">تماس با ما</li>
        </ol>
    </nav>

    <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">تماس با ما</h1>
    <p class="mt-2 text-sm text-ink-500">پیام خود را بفرستید؛ همکاران ما در ساعت کاری با شما تماس می‌گیرند.</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            @if ($sent)
                <div class="rounded-card border border-success-600/30 bg-card p-6 text-center" role="status">
                    <x-ts-icon icon="check-circle" class="mx-auto size-12 text-success-600" />
                    <p class="mt-3 font-bold text-ink-900">پیام شما رسید.</p>
                    <p class="mt-1 text-sm text-ink-500">به‌زودی با شماره {{ \App\Modules\Shared\Support\PersianDigits::toPersian($form[ContactMessage::COLUMN_MOBILE]) }} تماس می‌گیریم.</p>
                    <button type="button" wire:click="writeAnother" class="mt-4 inline-flex min-h-11 items-center rounded-card border border-line-300 px-5 text-sm font-medium text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">پیام دیگر</button>
                </div>
            @else
                <form wire:submit="send" class="space-y-4 rounded-card border border-line-200 bg-card p-4 md:p-6" novalidate>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($fields as [$key, $label, $attributes])
                            <div class="space-y-1.5">
                                <label for="contact-{{ $key }}" class="block text-sm font-medium text-ink-900">{{ $label }}</label>
                                <input id="contact-{{ $key }}" wire:model="form.{{ $key }}" {{ new \Illuminate\View\ComponentAttributeBag($attributes) }} class="h-11 {{ $inputClass($key) }}" @error('form.'.$key) aria-invalid="true" aria-describedby="contact-{{ $key }}-error" @enderror>
                                @error('form.'.$key) <p id="contact-{{ $key }}-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                            </div>
                        @endforeach

                        <div class="space-y-1.5">
                            <label for="contact-subject" class="block text-sm font-medium text-ink-900">موضوع</label>
                            <select id="contact-subject" wire:model="form.{{ ContactMessage::COLUMN_SUBJECT }}" class="h-11 {{ $inputClass(ContactMessage::COLUMN_SUBJECT) }}" @error('form.'.ContactMessage::COLUMN_SUBJECT) aria-invalid="true" aria-describedby="contact-subject-error" @enderror>
                                <option value="">انتخاب کنید</option>
                                @foreach ($subjects as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('form.'.ContactMessage::COLUMN_SUBJECT) <p id="contact-subject-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="contact-body" class="block text-sm font-medium text-ink-900">متن پیام</label>
                        <textarea id="contact-body" wire:model="form.{{ ContactMessage::COLUMN_BODY }}" rows="6" maxlength="2000" class="py-2 {{ $inputClass(ContactMessage::COLUMN_BODY) }}" @error('form.'.ContactMessage::COLUMN_BODY) aria-invalid="true" aria-describedby="contact-body-error" @enderror></textarea>
                        @error('form.'.ContactMessage::COLUMN_BODY) <p id="contact-body-error" class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="send" data-own-loading class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-card bg-primary-700 px-6 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 disabled:opacity-60 md:w-auto">
                        <x-ts-icon icon="arrow-path" class="size-4 animate-spin" wire:loading wire:target="send" />
                        ارسال پیام
                    </button>
                </form>
            @endif
        </div>

        <aside class="space-y-3 rounded-card border border-line-200 bg-card p-4 md:p-6" aria-label="راه‌های دیگر">
            <h2 class="font-bold text-ink-900">راه‌های دیگر</h2>
            @php
                $ways = array_values(array_filter([
                    $phone === null ? null : ['label' => \App\Modules\Shared\Support\PersianDigits::toPersian($phone), 'href' => $phoneUrl, 'icon' => 'phone', 'external' => true, 'ltr' => true],
                    $email === null ? null : ['label' => $email, 'href' => 'mailto:'.$email, 'icon' => 'envelope', 'external' => true, 'ltr' => true],
                    $whatsAppUrl === null ? null : ['label' => 'گفتگو در واتس‌اپ', 'href' => $whatsAppUrl, 'icon' => 'chat-bubble-left-right', 'external' => true],
                    $telegramUrl === null ? null : ['label' => 'گفتگو در تلگرام', 'href' => $telegramUrl, 'icon' => 'paper-airplane', 'external' => true],
                    ['label' => 'پیگیری سفارش', 'href' => route('orders.track'), 'icon' => 'truck', 'external' => false],
                    ['label' => 'پرسش‌های متداول', 'href' => route('faq'), 'icon' => 'question-mark-circle', 'external' => false],
                ]));
            @endphp
            <ul class="space-y-1">
                @foreach ($ways as $way)
                    <li>
                        <a href="{{ $way['href'] }}" @if ($way['external']) target="_blank" rel="noopener" @else wire:navigate @endif @if ($way['ltr'] ?? false) dir="ltr" @endif class="flex min-h-11 items-center gap-3 rounded-card px-2 text-sm text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                            <x-ts-icon :icon="$way['icon']" class="size-5 text-primary-700" />
                            {{ $way['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($workingHours !== null || $address !== null)
                <dl class="space-y-2 border-t border-line-200 pt-3 text-sm">
                    @if ($workingHours !== null)
                        <div class="flex gap-3"><dt class="sr-only">ساعت پاسخ‌گویی</dt><x-ts-icon icon="clock" class="size-5 shrink-0 text-primary-700" /><dd class="text-ink-900">{{ $workingHours }}</dd></div>
                    @endif
                    @if ($address !== null)
                        <div class="flex gap-3"><dt class="sr-only">نشانی</dt><x-ts-icon icon="map-pin" class="size-5 shrink-0 text-primary-700" /><dd class="whitespace-pre-line text-ink-900">{{ $address }}</dd></div>
                    @endif
                </dl>
            @endif
        </aside>
    </div>
</div>
