<?php

use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\HomeWidgetItems;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A home widget below the first screen (owner, 2026-11-28): nothing about it is queried until the visitor scrolls to
 * within 300 px of it. Until then a placeholder of about its height holds the place, so the page does not jump. A
 * widget with nothing to show by then leaves no trace (HomeWidgetItems).
 */
new class extends Component
{
    #[Locked]
    public int $widgetId;

    #[Locked]
    public string $type;

    public bool $ready = false;

    public function load(): void
    {
        $this->ready = true;
    }

    /**
     * The widget and its items, or null when it is no longer live or has nothing to show.
     *
     * @return array{widget: HomeWidget, items: Collection<int, Model>|null}|null
     */
    #[Computed]
    public function shown(): ?array
    {
        $widget = HomeWidget::query()->live()->find($this->widgetId);

        if ($widget === null) {
            return null;
        }

        $items = app(HomeWidgetItems::class)->for($widget);

        return $items instanceof Collection && $items->isEmpty() ? null : ['widget' => $widget, 'items' => $items];
    }

    /**
     * The height the widget takes at each screen width (measured with the demo data on 2026-11-28 at 375, 768, 1280
     * and 1920 px), so the page below does not move when it arrives.
     */
    public function placeholderClass(): string
    {
        return match (HomeWidgetType::tryFrom($this->type)) {
            HomeWidgetType::Slider => 'aspect-[2/1] md:aspect-[13/4]',
            HomeWidgetType::Collection, HomeWidgetType::FlashDeal => 'h-[330px] md:h-[368px]',
            HomeWidgetType::Brands, HomeWidgetType::Categories => 'h-[180px] xl:h-[196px]',
            HomeWidgetType::Trust => 'h-[86px] md:h-[102px] xl:h-[94px]',
            HomeWidgetType::Tags, null => 'h-[100px]',
        };
    }
};
?>

<div>
    @if ($ready)
        @if ($this->shown)
            @include('content::widgets.'.$this->shown['widget']->getType()->value, ['widget' => $this->shown['widget'], 'items' => $this->shown['items'], 'first' => false])
        @endif
    @else
        <div x-intersect.once.margin.300px="$wire.load()" class="{{ $this->placeholderClass() }} animate-pulse rounded-card bg-line-200/40" aria-hidden="true"></div>
    @endif
</div>
