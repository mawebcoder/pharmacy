<?php

declare(strict_types=1);

namespace App\Modules\Content\Queries;

use App\Modules\Catalog\Queries\CollectionProducts;
use App\Modules\Catalog\Queries\HomeItems;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\HomeWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * What each home widget shows now (roadmap D3.6, S13b). A widget is shown only with at least one item (owner,
 * 2026-11-07): a slider needs a visible banner, a collection rail an in-stock product of a visible collection, and
 * brands, categories and tags an active item. The trust block has no items and is always shown.
 */
final readonly class HomeWidgetItems
{
    public function __construct(
        private PlaceBanners $placeBanners,
        private HomeItems $homeItems,
        private CollectionProducts $collectionProducts,
    ) {}

    /**
     * The live widgets that have something to show, in the admin's order, each with its items (null for trust).
     *
     * @return list<array{widget: HomeWidget, items: Collection<int, Model>|null}>
     */
    public function shown(): array
    {
        $shown = [];

        foreach (HomeWidget::query()->live()->get() as $widget) {
            $items = $this->for($widget);

            if (! $items instanceof Collection || $items->isNotEmpty()) {
                $shown[] = ['widget' => $widget, 'items' => $items];
            }
        }

        return $shown;
    }

    /**
     * At most 6 items in order, or null for a widget without items (trust).
     *
     * @return Collection<int, Model>|null
     */
    public function for(HomeWidget $widget): ?Collection
    {
        /** @var Collection<int, Model>|null $items */
        $items = match ($widget->getType()) {
            HomeWidgetType::Slider => $this->placeBanners->for(BannerPlace::HomeWidget, $widget->getId()),
            HomeWidgetType::Collection => $widget->getCollectionId() === null ? new Collection : $this->collectionProducts->forCollection($widget->getCollectionId()),
            HomeWidgetType::Brands => $this->homeItems->brands($widget->getItemIds()),
            HomeWidgetType::Categories => $this->homeItems->categories($widget->getItemIds()),
            HomeWidgetType::Tags => $this->homeItems->tags($widget->getItemIds()),
            HomeWidgetType::Trust => null,
        };

        return $items;
    }

    /**
     * Why a widget is not on the home page now, in Persian for the admin, or null when it is shown.
     */
    public function hiddenReason(HomeWidget $widget): ?string
    {
        if (! $widget->isLive()) {
            return $widget->isActive() ? 'خارج از زمان‌بندی' : 'غیرفعال';
        }

        $items = $this->for($widget);

        if (! $items instanceof Collection || $items->isNotEmpty()) {
            return null;
        }

        return match ($widget->getType()) {
            HomeWidgetType::Slider => 'هیچ بنر فعالی ندارد',
            HomeWidgetType::Collection => $widget->getCollectionId() !== null && $this->homeItems->collection($widget->getCollectionId())?->getId() !== null
                ? 'کالکشن محصول موجودی ندارد'
                : 'کالکشن فعال نیست',
            default => 'هیچ‌کدام از موارد انتخاب‌شده فعال نیست',
        };
    }
}
