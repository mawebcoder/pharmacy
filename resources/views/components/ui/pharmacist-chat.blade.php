{{--
    «مشاوره با داروساز» / «سؤال خود را اینجا بپرسید» (owner, 2026-11-14): a button that first asks for confirmation (a
    bottom sheet on phones, a centred dialog from tablets up), then opens a chat with the support WhatsApp number and/or
    Telegram account from «تنظیمات فروشگاه»; with both set, the sheet offers both. Renders nothing while neither is set.
    The button shows the WhatsApp icon (Telegram's when only Telegram is set). $text is the draft typed into the chat;
    without it, the admin's general draft message is used.
    <x-ui.pharmacist-chat text="سلام، درباره «کرم اوره» سؤال دارم" class="…button classes…" />
    label-class="sr-only xl:not-sr-only" keeps the label for screen readers only below a breakpoint (icon button).
--}}
@props([
    'text' => null,
    'label' => 'سؤال خود را اینجا بپرسید',
    'iconClass' => 'size-5',
    'labelClass' => '',
])

@use('App\Modules\Settings\Queries\StoreSettings')

@php
    $settings = app(StoreSettings::class);
    $draft = $text ?? $settings->generalChatMessage();
    $channels = array_filter([
        'whatsapp' => $settings->whatsAppChatUrl($draft),
        'telegram' => $settings->telegramChatUrl($draft),
    ]);
    $names = ['whatsapp' => 'واتساپ', 'telegram' => 'تلگرام'];
    $only = count($channels) === 1 ? array_key_first($channels) : null;
@endphp

@if ($channels !== [])
    <div x-data="{ open: false }" class="contents">
        <button type="button" x-on:click="open = true" aria-haspopup="dialog" {{ $attributes }}>
            @if (isset($channels['whatsapp']))
                <x-ui.whatsapp-icon class="{{ $iconClass }}" />
            @else
                <x-ui.telegram-icon class="{{ $iconClass }}" />
            @endif
            <span class="{{ $labelClass }}">{{ $label }}</span>
        </button>

        <template x-teleport="body">
            <div x-show="open" x-cloak x-on:keydown.escape.window="open = false" class="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 md:items-center md:p-4" dir="rtl">
                <div
                    x-show="open"
                    x-trap.noscroll="open"
                    x-on:click.outside="open = false"
                    x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none"
                    x-transition:enter-start="translate-y-full md:translate-y-0 md:scale-95 md:opacity-0"
                    x-transition:enter-end="translate-y-0 md:scale-100 md:opacity-100"
                    role="dialog"
                    aria-modal="true"
                    aria-label="گفت‌وگو با داروساز"
                    class="w-full rounded-t-2xl bg-card p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] text-start shadow-card md:max-w-md md:rounded-card md:pb-5"
                >
                    <div x-swipe-close="open = false" class="-mx-5 -mt-5 mb-1 flex h-9 cursor-grab items-center justify-center md:hidden" aria-hidden="true">
                        <span class="h-1 w-10 rounded-full bg-line-300"></span>
                    </div>
                    <p class="text-lg font-bold text-ink-900">{{ $only ? 'ادامه در '.$names[$only].'؟' : 'گفت‌وگو با داروساز' }}</p>
                    <p class="mt-2 text-sm leading-7 text-ink-500">
                        {{ $only ? 'گفت‌وگو با داروساز ما در '.$names[$only].' باز می‌شود.' : 'گفت‌وگو در کدام پیام‌رسان باز شود؟' }}
                    </p>
                    <div class="mt-5 flex flex-col gap-2 md:flex-row md:flex-wrap">
                        @foreach ($channels as $channel => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" x-on:click="open = false" @class([
                                'inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-card px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700',
                                'bg-primary-700 text-white hover:bg-primary-800' => $loop->first,
                                'border border-primary-700 text-primary-700 hover:bg-primary-50' => ! $loop->first,
                            ])>
                                @if ($channel === 'whatsapp')
                                    <x-ui.whatsapp-icon class="size-5" />
                                @else
                                    <x-ui.telegram-icon class="size-5" />
                                @endif
                                {{ $only ? 'باز کردن '.$names[$channel] : $names[$channel] }}
                            </a>
                        @endforeach
                        <button type="button" x-on:click="open = false" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-card border border-line-300 px-4 text-sm text-ink-900 hover:bg-surface-50 md:basis-full">انصراف</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
