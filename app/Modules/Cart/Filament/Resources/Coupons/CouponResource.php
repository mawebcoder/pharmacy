<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\Coupons;

use App\Modules\Cart\Enums\CouponType;
use App\Modules\Cart\Enums\CouponUsageStatus;
use App\Modules\Cart\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Modules\Cart\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Modules\Cart\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Modules\Cart\Filament\Resources\Coupons\RelationManagers\CouponUsagesRelationManager;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Models\CouponUsage;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Coupons («کوپن‌ها», roadmap D3.4, B6 «فروش», S20). Deleting moves a coupon to the bin; its usages stay.
 */
final class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'فروش';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'کوپن';

    protected static ?string $pluralModelLabel = 'کوپن‌ها';

    protected static ?string $recordTitleAttribute = Coupon::COLUMN_CODE;

    public static function form(Schema $schema): Schema
    {
        return CouponForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['usages' => fn (Builder $usages): Builder => $usages->where(fn (Builder $holding): Builder => $holding
                ->where(CouponUsage::COLUMN_STATUS, CouponUsageStatus::Used)
                ->orWhere(fn (Builder $reserved): Builder => $reserved
                    ->where(CouponUsage::COLUMN_STATUS, CouponUsageStatus::Reserved)
                    ->where(CouponUsage::COLUMN_RESERVED_UNTIL, '>', now())))]))
            ->columns([
                TextColumn::make(Coupon::COLUMN_CODE)->label('کد')->searchable()->copyable()->extraAttributes(['dir' => 'ltr'])->weight('bold'),
                TextColumn::make(Coupon::COLUMN_TYPE)
                    ->label('تخفیف')
                    ->formatStateUsing(fn (Coupon $record): string => self::benefit($record))
                    ->description(fn (Coupon $record): ?string => $record->getMinOrderAmount() > 0 ? 'حداقل خرید '.Toman::format($record->getMinOrderAmount()) : null),
                TextColumn::make('usages_count')
                    ->label('استفاده')
                    ->tooltip('استفاده‌شده و رزرو تا پرداخت؛ اعمال در تسویه و آزادشده حساب نمی‌شوند.')
                    ->formatStateUsing(fn (Coupon $record, int $state): string => PersianDigits::toPersian($state).($record->getUsageLimitTotal() !== null ? ' از '.PersianDigits::toPersian($record->getUsageLimitTotal()) : ''))
                    ->sortable(),
                TextColumn::make(Coupon::COLUMN_ENDS_AT)
                    ->label('بازه')
                    ->formatStateUsing(fn (Coupon $record): string => self::period($record))
                    ->placeholder('همیشه')
                    ->sortable(),
                IconColumn::make(Coupon::COLUMN_IS_ACTIVE)->label('فعال')->boolean(),
            ])
            ->filters([TernaryFilter::make(Coupon::COLUMN_IS_ACTIVE)->label('فعال'), TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [CouponUsagesRelationManager::class];
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }

    /**
     * «۱۰٪ تا سقف ۱۵۰٬۰۰۰ تومان», «۵۰٬۰۰۰ تومان», «ارسال رایگان».
     */
    public static function benefit(Coupon $coupon): string
    {
        return match ($coupon->getType()) {
            CouponType::Percent => PersianDigits::toPersian($coupon->getValue()).'٪'.($coupon->getMaxDiscount() !== null ? ' تا سقف '.Toman::format($coupon->getMaxDiscount()) : ''),
            CouponType::Fixed => Toman::format($coupon->getValue()),
            CouponType::FreeShipping => 'ارسال رایگان',
        };
    }

    private static function period(Coupon $coupon): string
    {
        $from = $coupon->getStartsAt() instanceof Carbon ? 'از '.JalaliDate::display($coupon->getStartsAt(), 'j F') : '';
        $to = $coupon->getEndsAt() instanceof Carbon ? 'تا '.JalaliDate::display($coupon->getEndsAt(), 'j F Y') : '';

        return trim($from.' '.$to);
    }
}
