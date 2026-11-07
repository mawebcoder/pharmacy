<?php

use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Placeholder home page so the layout can be checked on a real URL. The real home page is built in S33.
 */
new #[Title('خانه')] class extends Component
{
    //
};
?>

<div class="space-y-6">
    <section class="rounded-card bg-primary-50 px-6 py-10 text-center md:py-16">
        <h1 class="text-2xl font-bold text-primary-800 xl:text-3xl">{{ config('app.display_name') }}</h1>
        <p class="mt-3 text-ink-500 md:text-lg">داروخانه آنلاین به‌زودی راه‌اندازی می‌شود.</p>
        <p class="mt-1 text-sm text-ink-500">آرایشی، بهداشتی، مکمل و تجهیزات پزشکی با ضمانت اصالت کالا.</p>
    </section>

    {{-- Need/nutrient chips (roadmap B9.2 item 2): the featured tags. --}}
    <x-catalog::tag-chips />
</div>
