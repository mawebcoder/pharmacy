<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Faqs\Pages;

use App\Modules\Content\Actions\ReorderFaqs;
use App\Modules\Content\Filament\Resources\Faqs\FaqResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

final class ManageFaqs extends ManageRecords
{
    protected static string $resource = FaqResource::class;

    /**
     * Drag and drop goes through ReorderFaqs.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless(auth('admin')->user()?->can('Update:Faq') ?? false, 403);

        app(ReorderFaqs::class)(array_values(array_map(intval(...), $order)));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('مشاهده صفحه')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('faq'), shouldOpenInNewTab: true),
            FaqResource::createAction(),
        ];
    }
}
