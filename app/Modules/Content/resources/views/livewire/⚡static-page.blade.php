<?php

use App\Modules\Content\Models\Page;
use App\Modules\Content\Support\PageContent;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A static page (roadmap B5 /pages/{slug}, S34): breadcrumb, title and the body, cleaned again before it is shown
 * (PageContent). Inactive, deleted or unknown pages are 404.
 */
new class extends Component
{
    public Page $page;

    public function mount(string $slug): void
    {
        $this->page = Page::query()->active()->where(Page::COLUMN_SLUG, mb_strtolower($slug))->firstOrFail();
    }

    public function render(): View
    {
        return $this->view([
            'html' => PageContent::toHtml($this->page),
        ])
            ->title($this->page->getSeoTitle() ?? $this->page->getTitle())
            ->layoutData(['description' => $this->page->getSeoDescription()]);
    }
};
?>

<article class="mx-auto max-w-3xl">
    <nav aria-label="مسیر صفحه" class="mb-4 text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700 hover:underline">خانه</a></li>
            <li aria-hidden="true"><x-ts-icon icon="chevron-left" class="size-3" /></li>
            <li aria-current="page" class="text-ink-900">{{ $page->getTitle() }}</li>
        </ol>
    </nav>

    <div class="rounded-card border border-line-200 bg-white px-4 py-6 md:px-8 md:py-8">
        <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">{{ $page->getTitle() }}</h1>

        @if ($html !== '')
            <div class="page-content mt-6">{!! $html !!}</div>
        @else
            <p class="mt-6 text-ink-500">محتوای این صفحه به‌زودی تکمیل می‌شود.</p>
        @endif
    </div>
</article>
