{{-- The account menu list (roadmap B9.9), shared by the desktop sidebar and the mobile dashboard. --}}
@props(['items', 'active'])

<nav aria-label="منوی حساب">
    <ul class="divide-y divide-line-200">
        @foreach ($items as $item)
            <li>
                <a
                    href="{{ $item['href'] }}"
                    wire:navigate
                    @if ($item['key'] === $active) aria-current="page" @endif
                    @class([
                        'flex min-h-12 items-center gap-3 px-4 text-sm hover:bg-surface-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700',
                        'font-bold text-primary-700' => $item['key'] === $active,
                        'text-ink-900' => $item['key'] !== $active,
                    ])
                >
                    <x-ts-icon :icon="$item['icon']" class="size-5 shrink-0" />
                    <span class="flex-1">{{ $item['label'] }}</span>
                    <x-ts-icon icon="chevron-left" class="size-4 text-ink-500" />
                </a>
            </li>
        @endforeach
        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex min-h-12 w-full items-center gap-3 px-4 text-sm text-danger-600 hover:bg-surface-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700">
                    <x-ts-icon icon="arrow-right-start-on-rectangle" class="size-5 shrink-0" />
                    خروج
                </button>
            </form>
        </li>
    </ul>
</nav>
