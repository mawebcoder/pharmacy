<?php

use App\Modules\Catalog\Models\Tag;
use Livewire\Component;

/**
 * Tag page (roadmap B5 /t/{slug}, B9.3): the products of a nutrient or need tag, with the other featured tags.
 */
new class extends Component
{
    public Tag $tag;

    public function mount(string $slug): void
    {
        $this->tag = Tag::query()->with('media')->where(Tag::COLUMN_SLUG, $slug)->firstOrFail();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->view()->title($this->tag->getNameFa());
    }
};
?>

<div class="space-y-6">
    <nav aria-label="مسیر صفحه" class="text-sm text-ink-500">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" wire:navigate class="hover:text-primary-700">خانه</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-ink-900">{{ $tag->getNameFa() }}</li>
        </ol>
    </nav>

    <header class="flex items-center gap-4">
        @if ($tag->getIcon())
            <span class="grid size-16 shrink-0 place-items-center rounded-card bg-primary-50">
                <x-media::picture :media="$tag->getIcon()" slot-name="category-icon" alt="" :priority="true" class="size-12 object-contain" />
            </span>
        @endif
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-ink-900 xl:text-3xl">{{ $tag->getNameFa() }}</h1>
            <p class="text-sm text-ink-500">{{ $tag->getType()->label() }}</p>
        </div>
    </header>

    <x-catalog::tag-chips :current="$tag" label="نیازهای دیگر" />

    <livewire:catalog::product-listing :tag-id="$tag->getId()" />
</div>
