{{--
    Page not found (owner, 2026-11-15): inside the storefront frame, a calm illustration — a large «۴۰۴» with a tilted
    capsule — a short explanation, and one button in the middle to every product, without filters (/products).
--}}
@component('layouts.app', ['title' => 'صفحه پیدا نشد', 'seo' => new \App\Modules\Seo\Data\SeoMeta(noindex: true)])
    <section class="relative mx-auto flex min-h-[60vh] max-w-xl flex-col items-center justify-center overflow-hidden px-2 py-10 text-center" aria-labelledby="not-found-title">
        {{-- Soft background glow, in the theme's colours (light and dark). --}}
        <div class="pointer-events-none absolute inset-x-0 top-6 -z-10 mx-auto size-72 rounded-full bg-primary-50 blur-3xl md:size-96" aria-hidden="true"></div>

        <div class="relative select-none" aria-hidden="true">
            <p class="bg-gradient-to-b from-primary-700 to-primary-700/30 bg-clip-text text-[7rem] leading-none font-black tracking-tight text-transparent md:text-[9rem]">۴۰۴</p>

            {{-- A capsule, tilted over the number. --}}
            <svg viewBox="0 0 120 56" class="absolute inset-x-0 -top-3 mx-auto w-24 -rotate-[24deg] drop-shadow-sm md:w-28" fill="none">
                <rect x="2" y="2" width="116" height="52" rx="26" class="fill-card stroke-line-300" stroke-width="3" />
                <path d="M60 2h32a26 26 0 0 1 0 52H60z" class="fill-accent-500" />
                <rect x="2" y="2" width="116" height="52" rx="26" class="stroke-line-300" stroke-width="3" />
                <path d="M74 16h18a12 12 0 0 1 12 12" class="stroke-white/70" stroke-width="4" stroke-linecap="round" />
            </svg>

            {{-- A few floating dots. --}}
            <span class="absolute -start-6 top-1/2 size-3 rounded-full bg-accent-500/70"></span>
            <span class="absolute -end-4 top-4 size-2 rounded-full bg-primary-700/60"></span>
            <span class="absolute -end-8 bottom-6 size-4 rounded-full bg-primary-50 ring-2 ring-primary-700/30"></span>
        </div>

        <h1 id="not-found-title" class="mt-6 text-2xl font-bold text-ink-900 md:text-3xl">این صفحه پیدا نشد</h1>
        <p class="mt-3 max-w-md leading-8 text-ink-500">
            شاید آدرس اشتباه وارد شده یا این صفحه جابه‌جا شده است. نگران نباشید؛ همه کالاهای داروخانه یک قدم آن‌طرف‌تر است.
        </p>

        <a href="{{ route('products.index') }}" class="mt-8 inline-flex h-12 items-center justify-center gap-2 rounded-card bg-primary-700 px-8 font-bold text-white shadow-card hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
            <x-ts-icon icon="shopping-bag" class="size-5" />
            مشاهده همه کالاها
        </a>
    </section>
@endcomponent
