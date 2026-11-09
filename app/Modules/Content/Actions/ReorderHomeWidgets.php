<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\HomeWidget;
use Illuminate\Support\Facades\DB;

/**
 * Saves the order of the home page widgets (drag and drop on «چیدمان صفحه اصلی»); that order is the home page.
 */
final class ReorderHomeWidgets
{
    /**
     * @param  list<int>  $widgetIds  in the new order
     */
    public function __invoke(array $widgetIds): void
    {
        $positions = array_flip($widgetIds);

        DB::transaction(function () use ($positions): void {
            foreach (HomeWidget::query()->whereKey(array_keys($positions))->get() as $widget) {
                $widget->setSort($positions[$widget->getId()] + 1)->save();
            }
        });
    }
}
