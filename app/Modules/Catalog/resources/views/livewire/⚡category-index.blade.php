<?php

use App\Modules\Catalog\Data\CategoryNode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Queries\CategoryTree;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * All categories (roadmap B9.1): the page behind the mobile "دسته‌ها" tab. Only the top level, as a grid of tiles with
 * each category's round icon (owner, 2026-11-14/15); a category's page shows its subcategories as chips. Tablet and
 * desktop also reach the categories through the header bar and mega-menu. Icons load in one query beside the cached
 * tree.
 */
new #[Title('دسته‌بندی‌ها')] class extends Component
{
    /**
     * @return list<CategoryNode>
     */
    #[Computed]
    public function roots(): array
    {
        return app(CategoryTree::class)->roots();
    }

    /**
     * Top-level categories with their icon media, keyed by id.
     *
     * @return array<int, Category>
     */
    #[Computed]
    public function withIcons(): array
    {
        return Category::query()->with('media')->whereKey(array_map(fn (CategoryNode $root): int => $root->id, $this->roots))->get()->keyBy(Category::COLUMN_ID)->all();
    }
};
?>

<div class="space-y-4">
    <x-content::banners place="categories_list" :priority="true" />

    <h1 class="text-2xl font-bold text-ink-900">دسته‌بندی‌ها</h1>

    @if ($this->roots === [])
        <div class="rounded-card border border-line-200 bg-card px-6 py-12 text-center text-ink-500">
            هنوز دسته‌ای ثبت نشده است.
        </div>
    @else
        <ul class="grid grid-cols-3 gap-3 md:grid-cols-4 xl:grid-cols-6">
            @foreach ($this->roots as $root)
                @php($icon = ($this->withIcons[$root->id] ?? null)?->getIcon())
                <li wire:key="category-{{ $root->id }}">
                    <a href="{{ $root->url() }}" wire:navigate.hover class="flex h-full flex-col items-center gap-2 rounded-card border border-line-200 bg-card p-3 text-center hover:border-primary-700 focus-visible:outline-2 focus-visible:outline-primary-700">
                        <span class="grid size-16 place-items-center rounded-full bg-primary-50 md:size-20">
                            @if ($icon)
                                <x-media::picture :media="$icon" slot-name="category-icon" alt="" class="size-10 object-contain md:size-12" />
                            @else
                                <x-ts-icon icon="squares-2x2" class="size-7 text-primary-700" />
                            @endif
                        </span>
                        <span class="line-clamp-2 text-sm font-medium text-ink-900">{{ $root->nameFa }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
