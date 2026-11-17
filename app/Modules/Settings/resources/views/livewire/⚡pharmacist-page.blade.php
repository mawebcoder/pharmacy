<?php

use App\Modules\Settings\Queries\Pharmacists;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «داروساز مسئول» (roadmap B5 /pharmacist, S35): the pharmacist in charge first, with photo, council number and bio,
 * then the store's other pharmacists.
 */
new class extends Component
{
    public function render(Pharmacists $pharmacists): View
    {
        return $this->view(['pharmacists' => $pharmacists->all()])
            ->title('داروساز مسئول')
            ->layoutData(['description' => 'داروساز مسئول و داروسازان داروخانه، با شماره نظام پزشکی.']);
    }
};
?>

@use('App\Modules\Shared\Support\PersianDigits')

<div class="mx-auto max-w-3xl">
    <nav aria-label="مسیر صفحه" class="mb-4 text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700 hover:underline">خانه</a></li>
            <li aria-hidden="true"><x-ts-icon icon="chevron-left" class="size-3" /></li>
            <li aria-current="page" class="text-ink-900">داروساز مسئول</li>
        </ol>
    </nav>

    <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">داروساز مسئول</h1>
    <p class="mt-2 text-sm text-ink-500">همه سفارش‌ها زیر نظر داروساز مسئول آماده و ارسال می‌شوند.</p>

    @forelse ($pharmacists as $pharmacist)
        <article wire:key="pharmacist-{{ $pharmacist->getId() }}" class="mt-6 flex flex-col gap-4 rounded-card border border-line-200 bg-card p-4 md:flex-row md:items-start md:gap-6 md:p-6">
            <div class="size-32 shrink-0 overflow-hidden rounded-full bg-surface-50 md:size-40">
                @if ($pharmacist->getPhoto() !== null)
                    <x-media::picture :media="$pharmacist->getPhoto()" slot-name="pharmacist-photo" :alt="$pharmacist->getFullName()" class="size-full object-cover" />
                @else
                    <x-ts-icon icon="user-circle" class="size-full text-line-300" />
                @endif
            </div>
            <div class="space-y-2">
                @if ($pharmacist->isResponsible())
                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700">
                        <x-ts-icon icon="shield-check" class="size-4" />
                        داروساز مسئول
                    </span>
                @endif
                <h2 class="text-lg font-bold text-ink-900">{{ $pharmacist->getFullName() }}</h2>
                <p class="text-sm text-ink-500">شماره نظام پزشکی: <span dir="ltr">{{ PersianDigits::toPersian($pharmacist->getLicenseNumber()) }}</span></p>
                @if ($pharmacist->getBio() !== null)
                    <p class="whitespace-pre-line text-sm leading-7 text-ink-900">{{ $pharmacist->getBio() }}</p>
                @endif
            </div>
        </article>
    @empty
        <p class="mt-6 rounded-card border border-line-200 bg-card p-6 text-center text-ink-500">معرفی داروساز مسئول به‌زودی اینجا قرار می‌گیرد.</p>
    @endforelse
</div>
