{{-- Mobile bottom navigation (roadmap B2 rule 1, B9.1): 5 items, 64px, safe-area aware, hidden from 768px. --}}
@php
    $items = [
        ['label' => 'خانه', 'icon' => 'home', 'href' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => 'دسته‌ها', 'icon' => 'squares-2x2', 'href' => '/categories', 'active' => request()->is('c/*', 'categories')],
        ['label' => 'جستجو', 'icon' => 'magnifying-glass', 'href' => '/search', 'active' => request()->is('search')],
        ['label' => 'سبد خرید', 'icon' => 'shopping-bag', 'href' => '/cart', 'active' => request()->is('cart')],
        ['label' => 'حساب من', 'icon' => 'user', 'href' => '/account', 'active' => request()->is('account', 'account/*')],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden" aria-label="منوی اصلی">
    <ul class="grid h-16 grid-cols-5">
        @foreach ($items as $item)
            <li>
                <a
                    href="{{ $item['href'] }}"
                    @if ($item['active']) aria-current="page" @endif
                    @class([
                        'flex h-full flex-col items-center justify-center gap-1 text-xs focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700',
                        'font-bold text-primary-700' => $item['active'],
                        'text-ink-500' => ! $item['active'],
                    ])
                >
                    <x-ts-icon :icon="$item['icon']" class="size-6" />
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
