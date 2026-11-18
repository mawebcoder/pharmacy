<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Pages;

use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shipping\Queries\VariantBoxCapacities;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * «بدون وزن یا کارتن» (roadmap S31, owner 2026-11-17/18): SKUs without a weight, or that fit no box. A missing weight
 * is packed as 300 g and its parcel marked an estimate; a SKU that fits no box is left for staff. Filled in on the
 * product's «تنوع‌ها» tab.
 */
final class VariantsWithoutShippingData extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'ارسال';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'بدون وزن یا کارتن';

    protected static ?string $title = 'تنوع‌های بدون وزن یا جا در کارتن';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('Update:Product') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::query()->with('product'))
            ->defaultSort('product_id')
            ->description('تنوع بدون وزن با فرض ۳۰۰ گرم بسته‌بندی و بسته‌اش تخمینی می‌شود؛ تنوعی که برای هیچ کارتنی عدد ندارد در هیچ کارتنی جا نمی‌گیرد و دستی بسته‌بندی می‌شود. در تب «تنوع‌ها» محصول وارد کنید.')
            ->columns([
                TextColumn::make('product.name_fa')->label('محصول')->searchable()->wrap(),
                TextColumn::make(ProductVariant::COLUMN_TITLE_FA)->label('تنوع'),
                IconColumn::make('has_weight')
                    ->label('وزن')
                    ->boolean()
                    ->state(fn (ProductVariant $record): bool => $record->getWeightG() !== null),
                IconColumn::make('has_boxes')
                    ->label('جا در کارتن‌ها')
                    ->boolean()
                    ->state(fn (ProductVariant $record): bool => in_array($record->getId(), app(VariantBoxCapacities::class)->variantIdsWithAnyBox(), true)),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('ویرایش محصول')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (ProductVariant $record): string => EditProduct::getUrl(['record' => $record->getProduct()?->getRouteKey() ?? $record->getProductId()]).'?relation=0'),
            ])
            ->emptyStateHeading('همه تنوع‌ها وزن و جا در کارتن دارند.');
    }

    /**
     * @return Builder<ProductVariant>
     */
    private static function query(): Builder
    {
        $withBoxes = app(VariantBoxCapacities::class)->variantIdsWithAnyBox();

        return ProductVariant::query()->where(fn (Builder $query): Builder => $query
            ->whereNull(ProductVariant::COLUMN_WEIGHT_G)
            ->orWhereNotIn(ProductVariant::COLUMN_ID, $withBoxes === [] ? [0] : $withBoxes));
    }
}
