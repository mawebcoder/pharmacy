<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Customers\RelationManagers;

use App\Modules\Identity\Models\Address;
use App\Modules\Locations\Queries\DeliveryLocations;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer's addresses on their admin page (roadmap S18), read-only: customers manage their own addresses.
 */
final class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'آدرس‌ها';

    protected static ?string $modelLabel = 'آدرس';

    protected static ?string $pluralModelLabel = 'آدرس‌ها';

    /** @var array<int, string>|null city id => «شهر، استان», loaded once per table */
    private ?array $cityLabels = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort(Address::COLUMN_IS_DEFAULT, 'desc')
            ->columns([
                TextColumn::make(Address::COLUMN_TITLE)->label('عنوان'),
                IconColumn::make(Address::COLUMN_IS_DEFAULT)->label('پیش‌فرض')->boolean(),
                TextColumn::make(Address::COLUMN_CITY_ID)
                    ->label('شهر')
                    ->formatStateUsing(fn (Address $record): string => $this->cityLabel($record->getCityId())),
                TextColumn::make(Address::COLUMN_FULL_ADDRESS)
                    ->label('نشانی')
                    ->formatStateUsing(fn (Address $record): string => PersianDigits::toPersian($record->getFullAddress().'، پلاک '.$record->getPlaque()))
                    ->wrap(),
                TextColumn::make(Address::COLUMN_RECEIVER_NAME)
                    ->label('گیرنده')
                    ->description(fn (Address $record): string => PersianDigits::toPersian($record->getReceiverMobile())),
                TextColumn::make(Address::COLUMN_POSTAL_CODE)->label('کد پستی')->formatStateUsing(fn (string $state): string => PersianDigits::toPersian($state)),
            ]);
    }

    private function cityLabel(int $cityId): string
    {
        $this->cityLabels ??= app(DeliveryLocations::class)->cityLabels(array_values(array_map(
            intval(...),
            Address::query()->ownedBy((int) $this->getOwnerRecord()->getKey())->pluck(Address::COLUMN_CITY_ID)->all(),
        )));

        return $this->cityLabels[$cityId] ?? '—';
    }
}
