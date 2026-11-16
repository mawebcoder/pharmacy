<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Data\HomeWidgetData;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\HomeWidget;

/**
 * The on/off button above the flash deals list (owner, 2026-11-17): turns the home page's one «پیشنهاد لحظه‌ای» row on
 * or off. Turning it on creates the row at the end of the page when there is none yet (its place is set in «چیدمان
 * صفحه اصلی»); its schedule is cleared so it shows whenever a deal runs.
 */
final readonly class SetFlashDealWidget
{
    public function __construct(private SaveHomeWidget $saveWidget) {}

    public function __invoke(bool $on): void
    {
        $widget = HomeWidget::query()->where(HomeWidget::COLUMN_TYPE, HomeWidgetType::FlashDeal)->first();

        if (! $widget instanceof HomeWidget) {
            if ($on) {
                $_ = ($this->saveWidget)(new HomeWidgetData(HomeWidgetType::FlashDeal, null, [], true, null, null));
            }

            return;
        }

        $widget->setIsActive($on);

        if ($on) {
            $widget->setStartsAt(null)->setEndsAt(null);
        }

        $widget->save();
    }

    /**
     * Whether the row is on the home page now (active and inside its schedule).
     */
    public static function isOn(): bool
    {
        return HomeWidget::query()->where(HomeWidget::COLUMN_TYPE, HomeWidgetType::FlashDeal)->live()->exists();
    }
}
