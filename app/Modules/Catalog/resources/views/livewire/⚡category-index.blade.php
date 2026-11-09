<?php

use App\Modules\Catalog\Data\CategoryNode;
use App\Modules\Catalog\Queries\CategoryTree;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * All categories (roadmap B9.1): the full-screen list behind the mobile "دسته‌ها" tab, two levels deep.
 * Tablet and desktop reach the same categories through the header bar and mega-menu.
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
};
?>

<div class="space-y-4">
    <x-content::banners place="categories_list" :priority="true" />

    <h1 class="text-2xl font-bold text-ink-900">دسته‌بندی‌ها</h1>

    @if ($this->roots === [])
        <div class="rounded-card border border-line-200 bg-white px-6 py-12 text-center text-ink-500">
            هنوز دسته‌ای ثبت نشده است.
        </div>
    @else
        <ul class="divide-y divide-line-200 overflow-hidden rounded-card border border-line-200 bg-white">
            @foreach ($this->roots as $root)
                <li wire:key="category-{{ $root->id }}" x-data="{ open: false }">
                    @if ($root->children === [])
                        <a href="{{ $root->url() }}" wire:navigate class="flex min-h-14 items-center px-4 font-bold text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700">
                            {{ $root->nameFa }}
                        </a>
                    @else
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open"
                            aria-controls="category-panel-{{ $root->id }}"
                            class="flex min-h-14 w-full items-center justify-between gap-3 px-4 text-start font-bold text-ink-900 hover:bg-surface-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-700"
                        >
                            {{ $root->nameFa }}
                            <x-ts-icon icon="chevron-down" class="size-5 text-ink-500 transition motion-reduce:transition-none" x-bind:class="open && 'rotate-180'" />
                        </button>
                        <ul id="category-panel-{{ $root->id }}" x-show="open" x-cloak class="bg-surface-50 pb-2">
                            <li>
                                <a href="{{ $root->url() }}" wire:navigate class="flex min-h-11 items-center px-6 text-sm font-bold text-primary-700 hover:underline">
                                    همه {{ $root->nameFa }}
                                </a>
                            </li>
                            @foreach ($root->children as $child)
                                <li wire:key="category-{{ $child->id }}">
                                    <a href="{{ $child->url() }}" wire:navigate class="flex min-h-11 items-center px-6 text-sm text-ink-900 hover:text-primary-700">
                                        {{ $child->nameFa }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
