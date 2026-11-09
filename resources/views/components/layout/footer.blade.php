{{--
    Site footer (roadmap B8.2, B9.2 item 8): trust block (x-layout.trust), help links, e-Namad slot.
    Page links point at the S34 static pages; contact details and badges come from settings in S35.
--}}
@php
    $columns = [
        'راهنمای خرید' => [
            'شیوه‌های ارسال' => '/pages/shipping',
            'روش‌های پرداخت' => '/pages/payment-methods',
            'بازگشت کالا' => '/pages/returns',
            'پرسش‌های متداول' => '/faq',
        ],
        'داروخانه' => [
            'درباره ما' => '/pages/about',
            'مجوزها' => '/pages/licenses',
            'داروساز مسئول' => '/pharmacist',
            'تماس با ما' => '/contact',
        ],
        'قوانین' => [
            'شرایط استفاده' => '/pages/terms',
            'حریم خصوصی' => '/pages/privacy',
            'پیگیری سفارش' => '/track',
        ],
    ];
@endphp

<footer class="mt-12 border-t border-line-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 md:px-6 xl:px-8">
        <x-layout.trust class="border-b border-line-200 py-8" />

        <div class="grid gap-8 py-8 md:grid-cols-4">
            @foreach ($columns as $title => $links)
                <div>
                    <h2 class="mb-3 text-sm font-bold text-ink-900">{{ $title }}</h2>
                    <ul class="space-y-1">
                        @foreach ($links as $label => $href)
                            <li><a href="{{ $href }}" class="inline-block py-1 text-sm text-ink-500 hover:text-primary-700">{{ $label }}</a></li>
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
