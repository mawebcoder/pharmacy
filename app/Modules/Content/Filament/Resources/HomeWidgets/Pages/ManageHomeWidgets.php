<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\HomeWidgets\Pages;

use App\Modules\Content\Actions\ReorderHomeWidgets;
use App\Modules\Content\Filament\Resources\HomeWidgets\HomeWidgetResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

final class ManageHomeWidgets extends ManageRecords
{
    protected static string $resource = HomeWidgetResource::class;

    /**
     * Drag and drop goes through ReorderHomeWidgets.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless(auth('admin')->user()?->can('Update:HomeWidget') ?? false, 403);

        app(ReorderHomeWidgets::class)(array_values(array_map(intval(...), $order)));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('پیش‌نمایش صفحه اصلی')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('home'), shouldOpenInNewTab: true),
            HomeWidgetResource::createAction(),
        ];
    }
}
