<?php

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Models\Faq;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «پرسش‌های متداول» (roadmap B5 /faq, S29): the active questions under their group's heading, in the admin's order,
 * each opening its answer in place. Groups without questions are left out.
 */
new class extends Component
{
    public function render(): View
    {
        $faqs = Faq::query()->active()->orderBy(Faq::COLUMN_SORT)->orderBy(Faq::COLUMN_ID)->get();
        $groups = [];

        foreach (FaqGroup::cases() as $group) {
            $questions = $faqs->filter(fn (Faq $faq): bool => $faq->getGroup() === $group)->values();

            if ($questions->isNotEmpty()) {
                $groups[] = ['group' => $group, 'questions' => $questions];
            }
        }

        return $this->view(['groups' => $groups])
            ->title('پرسش‌های متداول')
            ->layoutData(['description' => 'پاسخ پرسش‌های رایج درباره سفارش، ارسال، پرداخت و بازگشت کالا.']);
    }
};
?>

<div class="mx-auto max-w-3xl">
    <nav aria-label="مسیر صفحه" class="mb-4 text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700 hover:underline">خانه</a></li>
            <li aria-hidden="true"><x-ts-icon icon="chevron-left" class="size-3" /></li>
            <li aria-current="page" class="text-ink-900">پرسش‌های متداول</li>
        </ol>
    </nav>

    <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">پرسش‌های متداول</h1>

    @forelse ($groups as $index => $section)
        <section class="mt-6" aria-labelledby="faq-group-{{ $index }}">
            <h2 id="faq-group-{{ $index }}" class="mb-3 text-lg font-bold text-ink-900">{{ $section['group']->label() }}</h2>

            <div class="divide-y divide-line-200 overflow-hidden rounded-card border border-line-200 bg-card">
                @foreach ($section['questions'] as $faq)
                    <details class="group" wire:key="faq-{{ $faq->getId() }}">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700 md:text-base [&::-webkit-details-marker]:hidden">
                            <span>{{ $faq->getQuestion() }}</span>
                            <x-ts-icon icon="chevron-down" class="size-5 shrink-0 text-ink-500 transition-transform group-open:rotate-180" />
                        </summary>
                        <p class="whitespace-pre-line px-4 pb-4 text-sm leading-7 text-ink-900">{{ $faq->getAnswer() }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @empty
        <p class="mt-6 rounded-card border border-line-200 bg-card p-6 text-center text-ink-500">پرسشی هنوز ثبت نشده است.</p>
    @endforelse

    <div class="mt-8 flex flex-col items-start gap-3 rounded-card border border-line-200 bg-card p-4 md:flex-row md:items-center md:justify-between md:p-6">
        <p class="text-sm text-ink-900">پاسخ پرسش خود را پیدا نکردید؟</p>
        <a href="{{ route('contact') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-card bg-primary-700 px-5 text-sm font-bold text-white hover:bg-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
            <x-ts-icon icon="chat-bubble-left-right" class="size-5" />
            تماس با ما
        </a>
    </div>
</div>
