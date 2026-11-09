<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Support;

use App\Modules\Catalog\Data\VariantSummary;
use App\Modules\Pricing\Actions\CalculateVariantPrice;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\ProviderPriceChange;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the two supplier-price tables share (a SKU's suppliers, a supplier's SKUs; roadmap D3.2): the selling price a
 * cost gives with the commission, the two switches, the shared form fields and the change history. Switches and
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
     * cost × (1 + commission %), rounded up to 1,000 toman, capped by the printed price.
     */
    public static function sellingWithCommission(int $cost, ?VariantSummary $sku): string
    {
        if ($sku?->commissionPercent === null) {
            return 'کمیسیون تعیین نشده';
        }

        $selling = CalculateVariantPrice::sellingPrice($cost, $sku->commissionPercent);
        $capped = $sku->officialPrice !== null && $sku->officialPrice > 0 && $selling > $sku->officialPrice;

        return Toman::format($capped ? $sku->officialPrice : $selling).($capped ? ' (سقف روی جعبه)' : '');
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
            TextInput::make(ProviderPrice::COLUMN_LEAD_TIME_HOURS)->label('زمان تحویل')->numeric()->minValue(0)->maxValue(2000)->suffix('ساعت'),
            Toggle::make(ProviderPrice::COLUMN_IS_AVAILABLE)->label('موجود نزد تأمین‌کننده')->default(true),
            Toggle::make(ProviderPrice::COLUMN_IS_ENABLED)->label('فروش از این تأمین‌کننده')->default(true),
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
            ProviderPrice::COLUMN_LEAD_TIME_HOURS => $record->getLeadTimeHours(),
        ]), $record, self::adminId());

        return $column === ProviderPrice::COLUMN_IS_ENABLED ? $saved->isEnabled() : $saved->isAvailable();
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
