<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Livewire;

use App\Modules\Identity\Queries\AdminNames;
use App\Modules\Pricing\Enums\CommissionSource;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A SKU's price records as a table on its suppliers page (roadmap D3.2, owner 2026-11-09), like a relation manager:
 * when, why, supplier and cost, commission and its source, printed price, selling price, haraj, margin, notes and who.
 * The newest record is the price the website shows.
 */
final class PriceHistoryTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $variant;

    /** @var array<int, string>|null */
    private ?array $adminNames = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('تاریخچه قیمت')
            ->description('هر تغییر قیمت روی جعبه، حراج، کمیسیون یا تأمین‌کننده فعال یک رکورد تازه می‌سازد. ردیف اول قیمت فعلی سایت است؛ سفارش‌ها شناسه رکوردی را که با آن فروخته شده‌اند نگه می‌دارند.')
            ->query(fn (): Builder => VariantPrice::query()->with('provider')->where(VariantPrice::COLUMN_VARIANT_ID, $this->variant))
            ->defaultSort(VariantPrice::COLUMN_ID, 'desc')
            ->paginated([10, 25, 50])
            ->poll('10s')
            ->columns([
                TextColumn::make(VariantPrice::COLUMN_CREATED_AT)->label('زمان')->jalaliDateTime()->sortable(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                TextColumn::make(VariantPrice::COLUMN_REASON)
                    ->label('دلیل')
                    ->formatStateUsing(fn (PriceChangeReason $state): string => $state->label()),
                TextColumn::make('provider.name')->label('تأمین‌کننده')->placeholder('بدون تأمین‌کننده فعال'),
                TextColumn::make(VariantPrice::COLUMN_PROVIDER_COST)->label('قیمت خرید')->toman()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(VariantPrice::COLUMN_COMMISSION_PERCENT)
                    ->label('کمیسیون')
                    ->formatStateUsing(fn (float|string|null $state): string => $state === null ? '—' : PersianDigits::toPersian(rtrim(rtrim(number_format((float) $state, 2), '0'), '.')).'٪')
                    ->description(fn (VariantPrice $record): ?string => $record->getCommissionSource()?->label())
                    ->placeholder('—'),
                TextColumn::make(VariantPrice::COLUMN_OFFICIAL_PRICE)->label('روی جعبه')->toman()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(VariantPrice::COLUMN_PRICE)->label('قیمت فروش')->toman()->weight('bold'), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(VariantPrice::COLUMN_SALE_PRICE)->label('حراج')->toman()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
                TextColumn::make('margin')
                    ->label('سود هر عدد')
                    ->state(fn (VariantPrice $record): ?string => $record->getProviderCost() === null ? null : Toman::format(($record->getSalePrice() ?? $record->getPrice()) - $record->getProviderCost()))
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make(VariantPrice::COLUMN_FLAGS)
                    ->label('نکته')
                    ->badge()
                    ->color('warning')
                    ->state(fn (VariantPrice $record): array => array_map($this->flagLabel(...), $record->getFlags()))
                    ->placeholder('—'),
                TextColumn::make(VariantPrice::COLUMN_CHANGED_BY)
                    ->label('توسط')
                    ->state(fn (VariantPrice $record): string => $record->getChangedBy() === null ? 'سیستم' : ($this->adminName($record->getChangedBy()) ?? 'مدیر حذف‌شده')),
            ])
            ->filters([
                SelectFilter::make(VariantPrice::COLUMN_REASON)->label('دلیل')->options(PriceChangeReason::options()),
                SelectFilter::make(VariantPrice::COLUMN_COMMISSION_SOURCE)->label('منبع کمیسیون')->options(CommissionSource::options()),
            ])
            ->emptyStateHeading('رکورد قیمتی ثبت نشده است.');
    }

    public function render(): View
    {
        return view('pricing::livewire.price-history-table');
    }

    private function flagLabel(string $flag): string
    {
        return match ($flag) {
            VariantPrice::FLAG_SOLD_AT_PRINTED => 'فروش با قیمت روی جعبه',
            VariantPrice::FLAG_CAPPED_BY_PRINTED => 'سقف روی جعبه (سود کمتر از کمیسیون)',
            VariantPrice::FLAG_HARAJ_REMOVED => 'حراج حذف شد',
            VariantPrice::FLAG_CHANGE_OVER_15_PERCENT => 'تغییر بیش از ۱۵٪',
            default => $flag,
        };
    }

    private function adminName(int $adminId): ?string
    {
        $this->adminNames ??= app(AdminNames::class)->for(array_values(array_filter(array_map(
            intval(...),
            VariantPrice::query()->where(VariantPrice::COLUMN_VARIANT_ID, $this->variant)->whereNotNull(VariantPrice::COLUMN_CHANGED_BY)->distinct()->pluck(VariantPrice::COLUMN_CHANGED_BY)->all(),
        ))));

        return $this->adminNames[$adminId] ?? null;
    }
}
