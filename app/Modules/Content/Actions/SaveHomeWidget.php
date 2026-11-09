<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Data\HomeWidgetData;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Exceptions\HomeWidgetChangeRefused;
use App\Modules\Content\Models\HomeWidget;

/**
 * Creates or updates a home widget (roadmap D3.6, S13b): a collection rail needs its collection; brands, categories
 * and tags need 1 to 6 items. A new widget goes to the end of the page. A slider's banners are set with
 * SetPlaceBanners.
 */
final class SaveHomeWidget
{
    /**
     * @throws HomeWidgetChangeRefused
     */
    #[\NoDiscard]
    public function __invoke(HomeWidgetData $data, ?HomeWidget $widget = null): HomeWidget
    {
        if ($data->type === HomeWidgetType::Collection && ($data->settings['collection_id'] ?? null) === null) {
            throw HomeWidgetChangeRefused::collectionMissing();
        }

        $itemsKey = $data->type->itemsKey();

        if ($itemsKey !== null) {
            $count = count((array) ($data->settings[$itemsKey] ?? []));

            if ($count === 0 || $count > HomeWidgetType::MAX_ITEMS) {
                throw HomeWidgetChangeRefused::itemsCount(HomeWidgetType::MAX_ITEMS);
            }
        }

        $widget ??= (new HomeWidget)->setSort((int) HomeWidget::query()->max(HomeWidget::COLUMN_SORT) + 1);
        $widget->setType($data->type)
            ->setTitleFa($data->titleFa)
            ->setSettings($data->settings)
            ->setIsActive($data->isActive)
            ->setStartsAt($data->startsAt)
            ->setEndsAt($data->endsAt)
            ->save();

        return $widget;
    }
}
