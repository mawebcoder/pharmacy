<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Support;

use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Data\SupplierOffer;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\ProviderPriceChange;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the two supplier-price tables share (a SKU's suppliers, a supplier's SKUs; roadmap D3.2, S15b): each row's
 * commission and level and the selling price it gives, the switches (including «تأمین‌کننده انتخابی»), the shared form fields and the change history. Switches and
 * saves go through SaveProviderPrice, so the SKU is re-priced in the background.
 */
final class ProviderPriceColumns
{
    public static function canChange(): bool
    {
        return auth('admin')->user()?->can('Update:Provider') ?? false;
    }

    public static function adminId(): ?int
    {
        $id = auth('admin')->id();

        return $id === null ? null : (int) $id;
    }

    /**
     * Each row's commission (with its level) and the selling price it gives; $offerOf finds a row's offer.
     *
     * @param  Closure(ProviderPrice): ?SupplierOffer  $offerOf
     * @return list<TextColumn>
     */
    public static function offerColumns(Closure $offerOf): array
    {
        return [
            TextColumn::make('commission')
                ->label('کمیسیون')
                ->state(fn (ProviderPrice $record): ?string => self::percent($offerOf($record)?->commissionPercent))
                ->description(fn (ProviderPrice $record): ?string => $offerOf($record)?->commissionSource?->label())
                ->placeholder('تعیین نشده'),
            TextColumn::make('selling_with_commission')
                ->label('قیمت فروش با کمیسیون')
                ->state(fn (ProviderPrice $record): ?string => ($offer = $offerOf($record))?->sellingPrice === null ? null : Toman::format($offer->sellingPrice).($offer->cappedByPrinted ? ' (سقف روی جعبه)' : ''))
                ->placeholder('—')
                ->alignEnd(),
        ];
    }

    /**
     * «۱۲٪», or null.
     */
    public static function percent(?float $percent): ?string
    {
        return $percent === null ? null : PersianDigits::toPersian(rtrim(rtrim(number_format($percent, 2), '0'), '.')).'٪';
    }

    /**
     * @return list<ToggleColumn|TextColumn>
     */
    public static function columns(): array
    {
        return [
            ToggleColumn::make(ProviderPrice::COLUMN_IS_AVAILABLE)
                ->label('موجود نزد تأمین‌کننده')
                ->disabled(fn (): bool => ! self::canChange())
                ->updateStateUsing(fn (ProviderPrice $record, bool $state): bool => self::switch($record, ProviderPrice::COLUMN_IS_AVAILABLE, $state)),
            ToggleColumn::make(ProviderPrice::COLUMN_IS_ENABLED)
                ->label('فروش از این تأمین‌کننده')
                ->disabled(fn (): bool => ! self::canChange())
                ->updateStateUsing(fn (ProviderPrice $record, bool $state): bool => self::switch($record, ProviderPrice::COLUMN_IS_ENABLED, $state)),
            ToggleColumn::make(ProviderPrice::COLUMN_IS_PREFERRED)
                ->label('تأمین‌کننده انتخابی')
                ->tooltip('سایت تا وقتی این تأمین‌کننده قابل فروش است از او می‌فروشد، حتی اگر ارزان‌ترین نباشد. فقط یکی برای هر SKU.')
                ->disabled(fn (): bool => ! self::canChange())
                ->updateStateUsing(fn (ProviderPrice $record, bool $state): bool => self::switch($record, ProviderPrice::COLUMN_IS_PREFERRED, $state)),
            TextColumn::make(ProviderPrice::COLUMN_LEAD_TIME_HOURS)
                ->label('زمان تحویل')
                ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : PersianDigits::toPersian($state).' ساعت')
                ->placeholder('—'),
            TextColumn::make(ProviderPrice::COLUMN_UPDATED_AT)->label('آخرین تغییر')->jalaliDateTime(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
        ];
    }

    /**
     * Cost, lead time and the two switches (the supplier or SKU picker is added by each table).
     *
     * @return list<TextInput|Toggle>
     */
    public static function fields(): array
    {
        return [
            TextInput::make(ProviderPrice::COLUMN_COST)
                ->label('قیمت خرید از تأمین‌کننده')
                ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                ->required(),
            TextInput::make(ProviderPrice::COLUMN_COMMISSION_PERCENT)
                ->label('کمیسیون برای این SKU و تأمین‌کننده')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('٪')
                ->helperText('اختیاری. خالی: کمیسیون محصول یا برند (با تفکیک تأمین‌کننده اگر تعیین شده).'),
            TextInput::make(ProviderPrice::COLUMN_LEAD_TIME_HOURS)->label('زمان تحویل')->numeric()->minValue(0)->maxValue(2000)->suffix('ساعت'),
            Toggle::make(ProviderPrice::COLUMN_IS_AVAILABLE)->label('موجود نزد تأمین‌کننده')->default(true),
            Toggle::make(ProviderPrice::COLUMN_IS_ENABLED)->label('فروش از این تأمین‌کننده')->default(true),
            Toggle::make(ProviderPrice::COLUMN_IS_PREFERRED)
                ->label('تأمین‌کننده انتخابی')
                ->helperText('سایت از این تأمین‌کننده می‌فروشد تا وقتی قابل فروش است؛ انتخابی قبلی همین SKU خاموش می‌شود.'),
        ];
    }

    public static function historyAction(): Action
    {
        return Action::make('history')
            ->label('تاریخچه')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->modalHeading('تاریخچه قیمت خرید')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('بستن')
            ->modalContent(fn (ProviderPrice $record): View => view('pricing::filament.provider-price-history', ['changes' => self::changesOf($record)]));
    }

    private static function switch(ProviderPrice $record, string $column, bool $state): bool
    {
        abort_unless(self::canChange(), 403);

        $saved = app(SaveProviderPrice::class)(ProviderPriceData::fromArray([
            ProviderPrice::COLUMN_PROVIDER_ID => $record->getProviderId(),
            ProviderPrice::COLUMN_VARIANT_ID => $record->getVariantId(),
            ProviderPrice::COLUMN_COST => $record->getCost(),
            ProviderPrice::COLUMN_IS_AVAILABLE => $column === ProviderPrice::COLUMN_IS_AVAILABLE ? $state : $record->isAvailable(),
            ProviderPrice::COLUMN_IS_ENABLED => $column === ProviderPrice::COLUMN_IS_ENABLED ? $state : $record->isEnabled(),
            ProviderPrice::COLUMN_IS_PREFERRED => $column === ProviderPrice::COLUMN_IS_PREFERRED ? $state : $record->isPreferred(),
            ProviderPrice::COLUMN_COMMISSION_PERCENT => $record->getCommissionPercent(),
            ProviderPrice::COLUMN_LEAD_TIME_HOURS => $record->getLeadTimeHours(),
        ]), $record, self::adminId());

        return match ($column) {
            ProviderPrice::COLUMN_IS_ENABLED => $saved->isEnabled(),
            ProviderPrice::COLUMN_IS_PREFERRED => $saved->isPreferred(),
            default => $saved->isAvailable(),
        };
    }

    /**
     * @return Collection<int, ProviderPriceChange>
     */
    private static function changesOf(ProviderPrice $price): Collection
    {
        return ProviderPriceChange::query()
            ->where(ProviderPriceChange::COLUMN_VARIANT_ID, $price->getVariantId())
            ->where(ProviderPriceChange::COLUMN_PROVIDER_ID, $price->getProviderId())
            ->latest(ProviderPriceChange::COLUMN_ID)
            ->limit(50)
            ->get();
    }
}
