{{--
    Site footer (roadmap B8.2, B9.2 item 8): trust block (x-layout.trust), help links, e-Namad slot.
    The link columns are the footer menus («منوها», S34); contact details and badges come from settings in S35.
--}}
@php
    // The three footer columns are menus edited in the admin («منوها», S34; owner, 2026-11-17).
    $menuLinks = app(\App\Modules\Content\Queries\MenuLinks::class);
    $columns = array_values(array_filter(
        array_map(fn ($location): array => $menuLinks->for($location), [
            \App\Modules\Content\Enums\MenuLocation::Footer1,
            \App\Modules\Content\Enums\MenuLocation::Footer2,
            \App\Modules\Content\Enums\MenuLocation::Footer3,
        ]),
        fn (array $column): bool => $column['links'] !== [],
    ));
@endphp

<footer class="mt-12 border-t border-line-200 bg-card">
    <div class="mx-auto max-w-7xl px-4 md:px-6 xl:px-8">
        <x-layout.trust class="border-b border-line-200 py-8" />

        <div class="grid gap-8 py-8 md:grid-cols-4">
            @foreach ($columns as $column)
                <div>
                    @if ($column['title'] !== null)
                        <h2 class="mb-3 text-sm font-bold text-ink-900">{{ $column['title'] }}</h2>
                    @endif
                    <ul class="space-y-1">
                        @foreach ($column['links'] as $link)
                            <li><a href="{{ $link['href'] }}" @if ($link['newTab']) target="_blank" rel="noopener" @endif class="inline-block py-1 text-sm text-ink-500 hover:text-primary-700">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div>
                <h2 class="mb-3 text-sm font-bold text-ink-900">نمادهای اعتماد</h2>
                {{-- e-Namad embed code from settings (S35). --}}
                <div class="grid size-24 place-items-center rounded-card border border-dashed border-line-200 text-xs text-ink-500">نماد اعتماد</div>
            </div>
        </div>

        <p class="border-t border-line-200 py-4 text-center text-xs text-ink-500">
            © @jalali(now(), 'Y') {{ config('app.display_name') }}
        </p>
    </div>
</footer>
