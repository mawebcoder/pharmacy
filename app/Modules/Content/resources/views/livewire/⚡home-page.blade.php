<?php

use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\HomeWidgetItems;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The home page (roadmap B5, B9.2, S13b): the live widgets of «چیدمان صفحه اصلی» in the admin's order, each with 1 to
 * 6 items; a widget with nothing to show is left out (HomeWidgetItems). With no widget to show, a short welcome and
 * the featured tags.
 */
new #[Title('خانه')] class extends Component
{
    /**
     * @return list<array{widget: HomeWidget, items: Collection<int, Model>|null}>
     */
    #[Computed]
    public function widgets(): array
    {
        return app(HomeWidgetItems::class)->shown();
    }
};
?>

<div class="space-y-8 xl:space-y-10">
    @forelse ($this->widgets as ['widget' => $widget, 'items' => $items])
        <div wire:key="home-widget-{{ $widget->getId() }}">
            @include('content::widgets.'.$widget->getType()->value, ['widget' => $widget, 'items' => $items, 'first' => $loop->first])
        </div>
    @empty
        <section class="rounded-card bg-primary-50 px-6 py-10 text-center md:py-16">
            <h1 class="text-2xl font-bold text-primary-800 xl:text-3xl">{{ config('app.display_name') }}</h1>
            <p class="mt-3 text-ink-500 md:text-lg">آرایشی، بهداشتی، مکمل و تجهیزات پزشکی با ضمانت اصالت کالا.</p>
        </section>
        <x-catalog::tag-chips />
    @endforelse
</div>
