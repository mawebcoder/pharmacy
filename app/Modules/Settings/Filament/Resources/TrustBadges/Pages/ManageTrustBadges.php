<?php

declare(strict_types=1);

namespace App\Modules\Settings\Filament\Resources\TrustBadges\Pages;

use App\Modules\Settings\Actions\ReorderTrustBadges;
use App\Modules\Settings\Filament\Resources\TrustBadges\TrustBadgeResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageTrustBadges extends ManageRecords
{
    protected static string $resource = TrustBadgeResource::class;

    /**
     * Drag and drop goes through ReorderTrustBadges.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless(auth('admin')->user()?->can('Update:TrustBadge') ?? false, 403);

        app(ReorderTrustBadges::class)(array_values(array_map(intval(...), $order)));
    }

    protected function getHeaderActions(): array
    {
        return [TrustBadgeResource::createAction()];
    }
}
