{{--
    Account area frame (roadmap B9.9). Desktop: sidebar 3/12 with the customer and menu + content 9/12.
    Mobile and tablet: the sections as a row of tabs on top, swiped sideways (owner, 2026-11-19: no «پیشخوان»).
    A page inside a section (an order) passes :back and back-label: a link back to the section, on every size.
--}}
@props(['active', 'title', 'back' => null, 'backLabel' => null])

@php
    $customer = auth('customer')->user();
    $items = [
        ['key' => 'orders', 'label' => 'سفارش‌ها', 'icon' => 'shopping-bag', 'href' => route('account.orders')],
        ['key' => 'addresses', 'label' => 'آدرس‌ها', 'icon' => 'map-pin', 'href' => route('account.addresses')],
        ['key' => 'wishlist', 'label' => 'علاقه‌مندی‌ها', 'icon' => 'heart', 'href' => route('account.wishlist')],
        ['key' => 'reviews', 'label' => 'نظرات من', 'icon' => 'chat-bubble-bottom-center-text', 'href' => route('account.reviews')],
        ['key' => 'profile', 'label' => 'اطلاعات حساب', 'icon' => 'user', 'href' => route('account.profile')],
    ];
@endphp

<div class="grid gap-6 xl:grid-cols-12">
    <aside class="hidden xl:col-span-3 xl:block" aria-label="منوی حساب">
        <div class="sticky top-[calc(var(--header-height,72px)+1rem)] rounded-card border border-line-200 bg-card">
            <div class="flex items-center gap-3 border-b border-line-200 p-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-50 text-primary-700">
                    <x-ts-icon icon="user" class="size-6" />
                </span>
                <div class="min-w-0">
                    <p class="truncate font-bold text-ink-900">{{ \App\Modules\Shared\Support\PersianDigits::toPersian($customer->displayName()) }}</p>
                    <p class="text-sm text-ink-500" dir="ltr">{{ \App\Modules\Shared\Support\PersianDigits::toPersian($customer->getMobile()) }}</p>
                </div>
            </div>
            <x-profile::account-menu :items="$items" :active="$active" />
        </div>
    </aside>

    <section class="space-y-4 xl:col-span-9">
        <nav aria-label="منوی حساب" class="-mx-4 overflow-x-auto px-4 [scrollbar-width:none] xl:hidden">
            <ul class="flex w-max gap-2">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['href'] }}" wire:navigate @if ($item['key'] === $active) aria-current="page" @endif @class([
                            'inline-flex min-h-11 items-center gap-1.5 whitespace-nowrap rounded-full border px-4 text-sm focus-visible:outline-2 focus-visible:outline-primary-700',
                            'border-primary-700 bg-primary-700 font-bold text-white' => $item['key'] === $active,
                            'border-line-200 bg-card text-ink-900 hover:border-primary-700' => $item['key'] !== $active,
                        ])>
                            <x-ts-icon :icon="$item['icon']" class="size-4" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 items-center gap-1.5 whitespace-nowrap rounded-full border border-line-200 bg-card px-4 text-sm text-danger-600 hover:border-danger-600 focus-visible:outline-2 focus-visible:outline-primary-700">
                            <x-ts-icon icon="arrow-right-start-on-rectangle" class="size-4" />
                            خروج
                        </button>
                    </form>
                </li>
            </ul>
        </nav>

        @if ($back)
            <a href="{{ $back }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm text-primary-700 hover:underline">
                <x-ts-icon icon="chevron-right" class="size-4" />
                {{ $backLabel ?? 'بازگشت' }}
            </a>
        @endif

        <h1 class="text-xl font-bold text-ink-900 xl:text-2xl">{{ $title }}</h1>

        {{ $slot }}
    </section>
</div>
