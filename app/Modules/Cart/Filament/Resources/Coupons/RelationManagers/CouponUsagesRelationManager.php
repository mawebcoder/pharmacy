<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\Coupons\RelationManagers;

use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Models\CouponUsage;
use App\Modules\Identity\Queries\CustomerLabels;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Each customer's use of the coupon through checkout (roadmap S22): applied at checkout, reserved at the gateway,
 * used after payment or released when the order expired. Read-only.
 */
final class CouponUsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'استفاده‌ها';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(CouponUsage::COLUMN_CREATED_AT)
                    ->label('زمان')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
                TextColumn::make(CouponUsage::COLUMN_CUSTOMER_ID)
                    ->label('مشتری')
                    ->formatStateUsing(fn (int $state): string => PersianDigits::toPersian(app(CustomerLabels::class)->for([$state])[$state] ?? '#'.$state)),
                TextColumn::make(CouponUsage::COLUMN_STATUS)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (CouponUsageStatus $state): string => $state->label())
                    ->color(fn (CouponUsageStatus $state): string => $state->color())
                    ->description(fn (CouponUsage $record): ?string => $record->getStatus() === CouponUsageStatus::Reserved && $record->getReservedUntil() instanceof Carbon ? 'تا '.JalaliDate::display($record->getReservedUntil(), 'H:i') : null),
                TextColumn::make(CouponUsage::COLUMN_ORDER_ID)->label('سفارش')->placeholder('—'),
                TextColumn::make(CouponUsage::COLUMN_DISCOUNT_AMOUNT)
                    ->label('تخفیف')
                    ->toman(), // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
            ])
            ->filters([SelectFilter::make(CouponUsage::COLUMN_STATUS)->label('وضعیت')->options(CouponUsageStatus::options())])
            ->defaultSort(CouponUsage::COLUMN_CREATED_AT, 'desc')
            ->emptyStateHeading('هنوز کسی از این کد استفاده نکرده است.');
    }
}
