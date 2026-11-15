<?php

use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * «همه کالاها»: every product for sale in one list, with the usual filters and sort and no filter applied (owner,
 * 2026-11-15: where the 404 page sends people).
 */
new #[Title('همه کالاها')] class extends Component
{
};
?>

<div class="space-y-6">
    <nav aria-label="مسیر صفحه" class="text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700">خانه</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-ink-900">همه کالاها</li>
        </ol>
    </nav>

    <header>
        <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">همه کالاها</h1>
    </header>

    <livewire:catalog::product-listing />
</div>
