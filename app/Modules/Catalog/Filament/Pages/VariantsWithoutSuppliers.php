<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Pages;

use App\Modules\Catalog\Enums\StockStatus;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Filament\Pages\SkuSuppliers;
use App\Modules\Pricing\Filament\Support\PricingGuide;
use App\Modules\Pricing\Queries\SupplierPrices;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * «بدون تأمین‌کننده» (roadmap D3.2, S15): SKUs with no supplier row yet. They keep their old manual price and stock
 * until a supplier is added on their suppliers page. Only for admins who can see suppliers.
 */
final class VariantsWithoutSuppliers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'قیمت‌گذاری';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'بدون تأمین‌کننده';

    protected static ?string $title = 'تنوع‌های بدون تأمین‌کننده';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('ViewAny:Provider') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([PricingGuide::withoutSuppliers(), EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::query()->with('product'))
            ->defaultSort('product_id')
            ->columns([
                TextColumn::make('product.name_fa')->label('محصول')->searchable()->wrap(),
                TextColumn::make(ProductVariant::COLUMN_TITLE_FA)->label('تنوع'),
                TextColumn::make(ProductVariant::COLUMN_SKU)->label('SKU')->searchable(),
                TextColumn::make(ProductVariant::COLUMN_PRICE)->label('قیمت فعلی')->toman(), // @phpstan-ignore method.notFound (macro)
                TextColumn::make(ProductVariant::COLUMN_STOCK_STATUS)
                    ->label('موجودی')
                    ->badge()
                    ->formatStateUsing(fn (StockStatus $state): string => $state->label())
                    ->color(fn (StockStatus $state): string => $state->color()),
            ])
            ->recordActions([
                Action::make('suppliers')
                    ->label('تأمین‌کنندگان')
                    ->icon(Heroicon::OutlinedTruck)
                    ->url(fn (ProductVariant $record): string => SkuSuppliers::getUrl(['variant' => $record->getId()])),
            ])
            ->emptyStateHeading('همه تنوع‌ها تأمین‌کننده دارند.');
    }

    /**
     * @return Builder<ProductVariant>
     */
    private static function query(): Builder
    {
        return ProductVariant::query()->whereNotIn(ProductVariant::COLUMN_ID, app(SupplierPrices::class)->variantIdsWithSuppliers());
    }
}
