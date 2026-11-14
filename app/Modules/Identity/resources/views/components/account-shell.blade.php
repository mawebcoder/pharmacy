{{--
    Account area frame (roadmap B9.9). Desktop: sidebar 3/12 with the customer and menu + content 9/12.
    Mobile and tablet: the dashboard shows the menu as a list; other pages show a back link to «حساب من».
--}}
@props(['active', 'title'])

@php
    $customer = auth('customer')->user();
    $items = [
        ['key' => 'dashboard', 'label' => 'پیشخوان', 'icon' => 'squares-2x2', 'href' => route('account')],
        ['key' => 'orders', 'label' => 'سفارش‌ها', 'icon' => 'shopping-bag', 'href' => route('account.orders')],
        ['key' => 'addresses', 'label' => 'آدرس‌ها', 'icon' => 'map-pin', 'href' => route('account.addresses')],
        ['key' => 'wishlist', 'label' => 'علاقه‌مندی‌ها', 'icon' => 'heart', 'href' => route('account.wishlist')],
        ['key' => 'reviews', 'label' => 'نظرات من', 'icon' => 'chat-bubble-bottom-center-text', 'href' => route('account.reviews')],
        ['key' => 'profile', 'label' => 'اطلاعات حساب', 'icon' => 'user', 'href' => route('account.profile')],
    ];
@endphp

<div class="grid gap-6 xl:grid-cols-12">
    <aside class="hidden xl:col-span-3 xl:block" aria-label="منوی حساب">
        <div class="sticky top-[calc(var(--header-height,72px)+1rem)] rounded-card border border-line-200 bg-white">
            <div class="flex items-center gap-3 border-b border-line-200 p-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-50 text-primary-700">
                    <x-ts-icon icon="user" class="size-6" />
                </span>
                <div class="min-w-0">
                    <p class="truncate font-bold text-ink-900">{{ \App\Modules\Shared\Support\PersianDigits::toPersian($customer->displayName()) }}</p>
                    <p class="text-sm text-ink-500" dir="ltr">{{ \App\Modules\Shared\Support\PersianDigits::toPersian($customer->getMobile()) }}</p>
                </div>
            </div>
            <x-identity::account-menu :items="$items" :active="$active" />
        </div>
    </aside>

    <section class="space-y-4 xl:col-span-9">
        @if ($active === 'dashboard')
            <div class="rounded-card border border-line-200 bg-white xl:hidden">
                <x-identity::account-menu :items="array_slice($items, 1)" :active="$active" />
            </div>
        @else
            <a href="{{ route('account') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary-700 hover:underline xl:hidden">
                <x-ts-icon icon="chevron-right" class="size-4" />
                حساب من
            </a>
        @endif

        <h1 @class(['text-xl font-bold text-ink-900 xl:text-2xl', 'sr-only xl:not-sr-only' => $active === 'dashboard'])>{{ $title }}</h1>

        {{ $slot }}
    </section>
</div>
