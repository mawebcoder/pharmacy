<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\PriceImports\RelationManagers;

use App\Modules\Catalog\Data\VariantSummary;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Actions\CalculateVariantPrice;
use App\Modules\Pricing\Models\PriceImport;
use App\Modules\Pricing\Models\PriceImportRow;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The checked rows of a price import (roadmap D13.9): what each row changes (cost, switches, lead time), the selling
 * price the new cost would give with the commission and how much the cost moves, or why it cannot be imported.
 */
final class PriceImportRowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    protected static ?string $title = 'ردیف‌های فایل';

    protected static ?string $modelLabel = 'ردیف';

    protected static ?string $pluralModelLabel = 'ردیف‌ها';

    /** @var array<int, VariantSummary>|null */
    private ?array $summaries = null;

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
        $yesNo = fn (?bool $value): string => match ($value) {
            true => 'بله',
            false => 'خیر',
            null => '—',
        };

        return $table
            ->defaultSort(PriceImportRow::COLUMN_ROW_NUMBER)
            ->poll(fn (): ?string => $this->import()->getRowsTotal() === 0 ? '3s' : null)
            ->description('ردیف‌های سبز تغییر می‌کنند، خاکستری‌ها همان مقدار فعلی را دارند و قرمزها وارد نمی‌شوند. تا «اعمال» را نزنید چیزی تغییر نمی‌کند.')
            ->columns([
                TextColumn::make(PriceImportRow::COLUMN_ROW_NUMBER)->label('ردیف')->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state))->sortable(),
                TextColumn::make('outcome')
                    ->label('نتیجه')
                    ->badge()
                    ->state(fn (PriceImportRow $record): string => match (true) {
                        $record->getError() !== null => 'مشکل‌دار',
                        $record->isNew() => 'جدید',
                        $record->changes() => 'تغییر',
                        default => 'بدون تغییر',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'مشکل‌دار' => 'danger',
                        'جدید', 'تغییر' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('provider.name')->label('تأمین‌کننده')->placeholder(fn (PriceImportRow $record): string => $record->getRaw()[0] ?? '—'),
                TextColumn::make('sku')
                    ->label('محصول و SKU')
                    ->state(fn (PriceImportRow $record): string => ($sku = $this->summary($record)) instanceof VariantSummary ? $sku->productName : (($record->getRaw()[2] ?? '') ?: ($record->getRaw()[3] ?? '—')))
                    ->description(fn (PriceImportRow $record): ?string => ($sku = $this->summary($record)) instanceof VariantSummary ? $sku->title.' · '.$sku->sku.($record->getMatchedBy() === PriceImportRow::MATCHED_BY_BARCODE ? ' (با بارکد)' : '') : null)
                    ->wrap(),
                TextColumn::make(PriceImportRow::COLUMN_OLD_COST)->label('قیمت خرید فعلی')->toman()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(PriceImportRow::COLUMN_COST)
                    ->label('قیمت خرید جدید')
                    ->toman() // @phpstan-ignore method.notFound (macro)
                    ->description(fn (PriceImportRow $record): ?string => $this->changeLabel($record->costChangeShare()))
                    ->placeholder('—'),
                TextColumn::make(PriceImportRow::COLUMN_SELLING_PRICE)->label('قیمت فروش با کمیسیون')->toman()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(PriceImportRow::COLUMN_IS_AVAILABLE)
                    ->label('موجود نزد تأمین‌کننده')
                    ->state(fn (PriceImportRow $record): string => $this->switchChange($yesNo($record->getOldIsAvailable()), $yesNo($record->getIsAvailable()), $record)),
                TextColumn::make(PriceImportRow::COLUMN_IS_ENABLED)
                    ->label('فروش از این تأمین‌کننده')
                    ->state(fn (PriceImportRow $record): string => $this->switchChange($yesNo($record->getOldIsEnabled()), $yesNo($record->getIsEnabled()), $record)),
                TextColumn::make(PriceImportRow::COLUMN_ERROR)->label('مشکل')->color('danger')->wrap()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('outcome')
                    ->label('نتیجه')
                    ->options(['changes' => 'جدید یا تغییر', 'errors' => 'مشکل‌دار', 'same' => 'بدون تغییر'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'changes' => $query->whereNull(PriceImportRow::COLUMN_ERROR)->where(PriceImportRow::COLUMN_CHANGES, true),
                        'errors' => $query->whereNotNull(PriceImportRow::COLUMN_ERROR),
                        'same' => $query->whereNull(PriceImportRow::COLUMN_ERROR)->where(PriceImportRow::COLUMN_CHANGES, false),
                        default => $query,
                    }),
            ])
            ->emptyStateHeading(fn (): string => $this->import()->getError() ?? 'فایل در حال بررسی است…');
    }

    /**
     * «+۱۲٪», marked when the cost moves more than 15 %.
     */
    private function changeLabel(?float $share): ?string
    {
        if ($share === null || $share === 0.0) {
            return null;
        }

        $percent = PersianDigits::toPersian((string) round(abs($share) * 100)).'٪';
        $label = ($share > 0 ? '+' : '−').$percent;

        return abs($share) > CalculateVariantPrice::FLAG_CHANGE_SHARE ? $label.' (بیش از ۱۵٪)' : $label;
    }

    private function switchChange(string $old, string $new, PriceImportRow $row): string
    {
        return $row->getError() !== null ? '—' : ($row->isNew() || $old === $new ? $new : $old.' ← '.$new);
    }

    private function import(): PriceImport
    {
        /** @var PriceImport $import */
        $import = $this->getOwnerRecord();

        return $import;
    }

    private function summary(PriceImportRow $row): ?VariantSummary
    {
        if ($row->getVariantId() === null) {
            return null;
        }

        $this->summaries ??= app(VariantPricingInputs::class)->summaries(array_values(array_map(
            intval(...),
            $this->import()->rows()->whereNotNull(PriceImportRow::COLUMN_VARIANT_ID)->pluck(PriceImportRow::COLUMN_VARIANT_ID)->all(),
        )));

        return $this->summaries[$row->getVariantId()] ?? null;
    }
}
