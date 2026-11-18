{{--
    Site footer (roadmap B8.2, B9.2 item 8): trust block with counters (x-layout.trust), link columns, contact details
    with social links, and the trust badges (e-Namad).
    The link columns are the footer menus («منوها», S29); contact details, social links and badges are set in the admin
    (S30).
--}}
@php
    // The three footer columns are menus edited in the admin («منوها», S29; owner, 2026-11-17).
    $menuLinks = app(\App\Modules\Content\Queries\MenuLinks::class);
    $columns = array_values(array_filter(
        array_map(fn ($location): array => $menuLinks->for($location), [
            \App\Modules\Content\Enums\MenuLocation::Footer1,
            \App\Modules\Content\Enums\MenuLocation::Footer2,
            \App\Modules\Content\Enums\MenuLocation::Footer3,
        ]),
        fn (array $column): bool => $column['links'] !== [],
    ));
    // Contact details, social links and trust badges come from the admin (S30); empty ones are left out.
    $settings = app(\App\Modules\Settings\Queries\StoreSettings::class);
    $contact = array_values(array_filter([
        $settings->phone() === null ? null : ['icon' => 'phone', 'text' => \App\Modules\Shared\Support\PersianDigits::toPersian($settings->phone()), 'href' => $settings->phoneUrl(), 'ltr' => true],
        $settings->email() === null ? null : ['icon' => 'envelope', 'text' => $settings->email(), 'href' => 'mailto:'.$settings->email(), 'ltr' => true],
        $settings->workingHours() === null ? null : ['icon' => 'clock', 'text' => $settings->workingHours(), 'href' => null, 'ltr' => false],
        $settings->address() === null ? null : ['icon' => 'map-pin', 'text' => $settings->address(), 'href' => null, 'ltr' => false],
    ]));
    $socialLinks = $settings->socialLinks();
    $badges = app(\App\Modules\Settings\Queries\TrustBadges::class)->shown();
@endphp

<footer class="mt-12 border-t border-line-200 bg-card">
    <div class="mx-auto max-w-7xl px-4 md:px-6 xl:px-8">
        <x-layout.trust class="py-8" />
        <x-layout.trust-counters class="pb-8" />
        <div class="border-b border-line-200" aria-hidden="true"></div>

        <div class="grid gap-8 py-8 md:grid-cols-3 xl:grid-cols-5">
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

            @if ($contact !== [] || $socialLinks !== [])
                <div>
                    <h2 class="mb-3 text-sm font-bold text-ink-900">ارتباط با ما</h2>
                    <ul class="space-y-2">
                        @foreach ($contact as $item)
                            <li class="flex items-start gap-2 text-sm text-ink-500">
                                <x-ts-icon :icon="$item['icon']" class="mt-0.5 size-5 shrink-0" />
                                @if ($item['href'] !== null)
                                    <a href="{{ $item['href'] }}" @if ($item['ltr']) dir="ltr" @endif class="hover:text-primary-700">{{ $item['text'] }}</a>
                                @else
                                    <span class="whitespace-pre-line">{{ $item['text'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($socialLinks !== [])
                        <ul class="mt-4 flex flex-wrap gap-2" aria-label="شبکه‌های اجتماعی">
                            @foreach ($socialLinks as $social)
                                <li>
                                    <a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="{{ $social['platform']->label() }}" title="{{ $social['platform']->label() }}" class="grid size-11 place-items-center rounded-full border border-line-200 text-ink-500 hover:border-primary-700 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700">
                                        <x-settings::social-icon :platform="$social['platform']" class="size-5" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if ($badges !== [])
                <div>
                    <h2 class="mb-3 text-sm font-bold text-ink-900">نمادهای اعتماد</h2>
                    <ul class="flex flex-wrap items-center gap-3">
                        @foreach ($badges as $badge)
                            <li class="grid size-24 place-items-center overflow-hidden rounded-card border border-line-200 bg-white p-1 [&_img]:max-h-full [&_img]:max-w-full" title="{{ $badge['title'] }}">
                                @if ($badge['html'] !== null)
                                    {!! $badge['html'] !!}
                                @elseif ($badge['url'] !== null)
                                    <a href="{{ $badge['url'] }}" target="_blank" rel="noopener"><x-media::picture :media="$badge['image']" slot-name="trust-badge" :alt="$badge['title']" class="max-h-20 w-auto object-contain" /></a>
                                @else
                                    <x-media::picture :media="$badge['image']" slot-name="trust-badge" :alt="$badge['title']" class="max-h-20 w-auto object-contain" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <p class="border-t border-line-200 py-4 text-center text-xs text-ink-500">
            © @jalali(now(), 'Y') {{ config('app.display_name') }}
        </p>
    </div>
</footer>
