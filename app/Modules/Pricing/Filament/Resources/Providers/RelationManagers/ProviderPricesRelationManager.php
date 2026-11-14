<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Resources\Providers\RelationManagers;

use App\Modules\Catalog\Data\VariantSummary;
use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Actions\DeleteProviderPrice;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ChosenSupplier;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Data\SupplierOffer;
use App\Modules\Pricing\Filament\Pages\SkuSuppliers;
use App\Modules\Pricing\Filament\Support\ProviderPriceColumns;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Queries\ActiveSuppliers;
use App\Modules\Pricing\Queries\SupplierPrices;
use App\Modules\Shared\Support\Toman;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A supplier's SKUs with their price information (owner, 2026-11-09): the SKU, our cost from this supplier, the
 * selling price it gives with the commission, what the website sells at now and whether it sells from this
 * supplier, the two switches and lead time. Changes go through SaveProviderPrice, so the SKU is re-priced.
 */
final class ProviderPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'SKUهای این تأمین‌کننده';

    protected static ?string $modelLabel = 'SKU';

    protected static ?string $pluralModelLabel = 'SKUها';

    /** @var array<int, VariantSummary>|null */
    private ?array $summaries = null;

    /** @var array<int, int|null>|null variant id => active supplier price id */
    private ?array $activePriceIds = null;

    /** @var array<int, SupplierOffer>|null */
    private ?array $offers = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! ProviderPriceColumns::canChange();
    }

    public function table(Table $table): Table
    {
        $canChange = ProviderPriceColumns::canChange(...);

        return $table
            ->recordTitleAttribute(ProviderPrice::COLUMN_VARIANT_ID)
            ->defaultSort(ProviderPrice::COLUMN_UPDATED_AT, 'desc')
            ->description('قیمت خرید هر SKU از این تأمین‌کننده و قیمت فروشی که با کمیسیون می‌سازد. سایت از ارزان‌ترین تأمین‌کننده فعالِ هر SKU می‌فروشد؛ ستون «سایت» نشان می‌دهد آیا آن تأمین‌کننده همین است.')
            ->columns([
                TextColumn::make('sku')
                    ->label('محصول و SKU')
                    ->state(fn (ProviderPrice $record): string => ($summary = $this->summary($record)) instanceof VariantSummary ? $summary->productName : 'SKU #'.$record->getVariantId())
                    ->description(fn (ProviderPrice $record): ?string => ($summary = $this->summary($record)) instanceof VariantSummary ? $summary->title.' · '.$summary->sku : null)
                    ->url(fn (ProviderPrice $record): string => SkuSuppliers::getUrl(['variant' => $record->getVariantId()]))
                    ->wrap(),
                TextColumn::make(ProviderPrice::COLUMN_COST)->label('قیمت خرید')->toman()->sortable(), // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                ...ProviderPriceColumns::offerColumns(fn (ProviderPrice $record): ?SupplierOffer => $this->offers()[$record->getId()] ?? null),
                TextColumn::make('website_price')
                    ->label('قیمت فعلی سایت')
                    ->state(fn (ProviderPrice $record): ?string => ! ($summary = $this->summary($record)) instanceof VariantSummary || $summary->price === 0 ? null : Toman::format($summary->salePrice ?? $summary->price))
                    ->description(fn (ProviderPrice $record): ?string => $this->summary($record)?->salePrice === null ? null : 'حراج')
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('selling_from_here')
                    ->label('سایت')
                    ->badge()
                    ->state(fn (ProviderPrice $record): string => ($this->activePriceIds()[$record->getVariantId()] ?? null) === $record->getId() ? 'از این تأمین‌کننده' : 'از دیگری یا روی جعبه')
                    ->color(fn (string $state): string => $state === 'از این تأمین‌کننده' ? 'success' : 'gray'),
                ...ProviderPriceColumns::columns(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن SKU')
                    ->modalHeading('افزودن SKU به این تأمین‌کننده')
                    ->visible($canChange)
                    ->schema($this->priceForm(withSku: true))
                    ->using(fn (array $data): ProviderPrice => app(SaveProviderPrice::class)($this->priceData($data), adminId: ProviderPriceColumns::adminId())),
            ])
            ->recordActions([
                Action::make('sku')
                    ->label('صفحه SKU')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (ProviderPrice $record): string => SkuSuppliers::getUrl(['variant' => $record->getVariantId()])),
                EditAction::make()
                    ->visible($canChange)
                    ->schema($this->priceForm(withSku: false))
                    ->using(fn (ProviderPrice $record, array $data): ProviderPrice => app(SaveProviderPrice::class)($this->priceData([...$data, ProviderPrice::COLUMN_VARIANT_ID => $record->getVariantId()]), $record, ProviderPriceColumns::adminId())),
                ProviderPriceColumns::historyAction(),
                DeleteAction::make()
                    ->visible($canChange)
                    ->modalDescription('این SKU از تأمین‌کننده برداشته می‌شود و در تاریخچه می‌ماند؛ قیمت فروش SKU دوباره محاسبه می‌شود.')
                    ->using(function (ProviderPrice $record): bool {
                        app(DeleteProviderPrice::class)($record, ProviderPriceColumns::adminId());

                        return true;
                    }),
            ])
            ->emptyStateHeading('هنوز SKUای برای این تأمین‌کننده ثبت نشده است.');
    }

    private function provider(): Provider
    {
        /** @var Provider $provider */
        $provider = $this->getOwnerRecord();

        return $provider;
    }

    /**
     * @return list<Select|TextInput|Toggle>
     */
    private function priceForm(bool $withSku): array
    {
        $sku = ImageSelect::skus(Select::make(ProviderPrice::COLUMN_VARIANT_ID))
            ->label('SKU')
            ->required()
            ->rules(fn (): array => [function (string $attribute, mixed $value, \Closure $fail): void {
                if (ProviderPrice::query()->where(ProviderPrice::COLUMN_PROVIDER_ID, $this->provider()->getId())->where(ProviderPrice::COLUMN_VARIANT_ID, (int) $value)->exists()) {
                    $fail('این SKU قبلاً برای این تأمین‌کننده ثبت شده است.');
                }
            }]);

        return $withSku ? [$sku, ...ProviderPriceColumns::fields()] : ProviderPriceColumns::fields();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function priceData(array $data): ProviderPriceData
    {
        return ProviderPriceData::fromArray([...$data, ProviderPrice::COLUMN_PROVIDER_ID => $this->provider()->getId()]);
    }

    private function summary(ProviderPrice $price): ?VariantSummary
    {
        return $this->summaries()[$price->getVariantId()] ?? null;
    }

    /**
     * @return array<int, VariantSummary>
     */
    private function summaries(): array
    {
        return $this->summaries ??= app(VariantPricingInputs::class)->summaries($this->variantIds());
    }

    /**
     * The row each SKU sells from (ActiveSuppliers), keyed by variant id; null when it sells at the printed price.
     *
     * @return array<int, int|null>
     */
    private function activePriceIds(): array
    {
        return $this->activePriceIds ??= array_map(
            fn (?ChosenSupplier $chosen): ?int => $chosen?->offer->price->getId(),
            app(ActiveSuppliers::class)->forVariants($this->variantIds()),
        );
    }

    /**
     * This supplier's offer for each of its SKUs (commission, level, selling price), keyed by row id.
     *
     * @return array<int, SupplierOffer>
     */
    private function offers(): array
    {
        if ($this->offers === null) {
            $this->offers = [];

            foreach (app(ActiveSuppliers::class)->offers(app(VariantPricingInputs::class)->for($this->variantIds())) as $variantOffers) {
                foreach ($variantOffers as $offer) {
                    if ($offer->price->getProviderId() === $this->provider()->getId()) {
                        $this->offers[$offer->price->getId()] = $offer;
                    }
                }
            }
        }

        return $this->offers;
    }

    /**
     * @return list<int>
     */
    private function variantIds(): array
    {
        return app(SupplierPrices::class)->variantIdsOfProvider($this->provider()->getId());
    }
}
