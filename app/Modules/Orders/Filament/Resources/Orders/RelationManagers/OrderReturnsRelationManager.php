<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\RelationManagers;

use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use App\Modules\Orders\Filament\Resources\Returns\Tables\OrderReturnsTable;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Queries\OrderReturns;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * «مرجوعی‌ها» on the order's page (S27, owner 2026-11-19): each return with full or partial, status, refund and Tipax's
 * price; a row opens the return. «ثبت مرجوعی» opens a new one for this order.
 */
final class OrderReturnsRelationManager extends RelationManager
{
    protected static string $relationship = 'returns';

    protected static ?string $title = 'مرجوعی‌ها';

    protected static ?string $modelLabel = 'مرجوعی';

    protected static ?string $pluralModelLabel = 'مرجوعی‌ها';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('viewAny', OrderReturn::class) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return OrderReturnsTable::configure($table, withOrder: false)
            ->paginated(false)
            ->headerActions([
                Action::make('createReturn')
                    ->label('ثبت مرجوعی')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn (OrderReturns $returns): bool => (auth('admin')->user()?->can('create', OrderReturn::class) ?? false) && $returns->canReturn($this->order()))
                    ->url(fn (): string => OrderReturnResource::getUrl('create', ['order' => $this->order()->getId()])),
            ]);
    }

    private function order(): Order
    {
        /** @var Order */
        return $this->getOwnerRecord();
    }
}
