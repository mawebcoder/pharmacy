<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Filament\Pages;

use App\Modules\Catalog\Data\VariantSummary;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Actions\DeleteProviderPrice;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Filament\Resources\Providers\ProviderResource;
use App\Modules\Pricing\Filament\Support\PricingGuide;
use App\Modules\Pricing\Filament\Support\ProviderPriceColumns;
use App\Modules\Pricing\Http\Requests\StoreProviderPriceRequest;
use App\Modules\Pricing\Livewire\PriceHistoryTable;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Queries\SupplierPrices;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * One SKU's suppliers (roadmap D3.2, S14/S15), opened from the product's SKU table: each supplier's cost and the
 * selling price it would give with the commission, the two switches («موجود نزد تأمین‌کننده», «فروش از این
 * تأمین‌کننده»), which one the website sells from now, and the SKU's price records (PriceHistoryTable). Supplier prices are our costs:
 * only admins who can see suppliers open this page; changes need Update:Provider.
 */
final class SkuSuppliers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'sku-suppliers';

    protected static bool $shouldRegisterNavigation = false;

    public int $variant;

    public static function getRoutePath(Panel $panel): string
    {
        return '/skus/{variant}/suppliers';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('viewAny', Provider::class) ?? false;
    }

    public function mount(int $variant): void
    {
        abort_unless($this->summary() instanceof VariantSummary, 404);

        $this->variant = $variant;
    }

    public function getTitle(): string
    {
        $summary = $this->summary();

        return $summary instanceof VariantSummary ? 'تأمین‌کنندگان: '.$summary->productName.' · '.$summary->title : 'تأمین‌کنندگان SKU';
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        $summary = $this->summary();

        return $summary instanceof VariantSummary
            ? [route('filament.admin.resources.products.edit', ['record' => $summary->productRouteKey]) => $summary->productName, 'تأمین‌کنندگان SKU']
            : [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('این SKU')
                ->schema([View::make('pricing::filament.sku-summary')->viewData(fn (): array => [
                    'summary' => $this->summary(),
                    'active' => $this->activeSupplier(),
                ])]),
            PricingGuide::suppliers(),
            EmbeddedTable::make(),
            Livewire::make(PriceHistoryTable::class, fn (): array => ['variant' => $this->variant])->key('price-history'),
        ]);
    }

    public function table(Table $table): Table
    {
        $canChange = ProviderPriceColumns::canChange(...);

        return $table
            ->heading('تأمین‌کنندگان این SKU')
            ->query(fn (): Builder => ProviderPrice::query()->with('provider')->where(ProviderPrice::COLUMN_VARIANT_ID, $this->variant))
            ->defaultSort(ProviderPrice::COLUMN_COST)
            ->paginated(false)
            ->columns([
                TextColumn::make('provider.name')
                    ->label('تأمین‌کننده')
                    ->url(fn (ProviderPrice $record): string => ProviderResource::getUrl('view', ['record' => $record->getProviderId()]))
                    ->description(fn (ProviderPrice $record): ?string => $record->getProvider()?->isActive() === false ? 'تأمین‌کننده غیرفعال است' : null),
                TextColumn::make(ProviderPrice::COLUMN_COST)->label('قیمت خرید')->toman(), // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                TextColumn::make('selling_with_commission')
                    ->label('قیمت فروش با کمیسیون')
                    ->state(fn (ProviderPrice $record): string => ProviderPriceColumns::sellingWithCommission($record->getCost(), $this->summary()))
                    ->alignEnd(),
                TextColumn::make('active')
                    ->label('سایت')
                    ->badge()
                    ->state(fn (ProviderPrice $record): ?string => $this->activeSupplier()?->getId() === $record->getId() ? 'فعال: فروش از این تأمین‌کننده' : null)
                    ->color('success'),
                ...ProviderPriceColumns::columns(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن تأمین‌کننده')
                    ->modalHeading('افزودن تأمین‌کننده به این SKU')
                    ->visible($canChange)
                    ->schema($this->priceForm())
                    ->using(fn (array $data): ProviderPrice => app(SaveProviderPrice::class)($this->priceData($data), adminId: ProviderPriceColumns::adminId())),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible($canChange)
                    ->schema($this->priceForm())
                    ->using(fn (ProviderPrice $record, array $data): ProviderPrice => app(SaveProviderPrice::class)($this->priceData($data), $record, ProviderPriceColumns::adminId())),
                ProviderPriceColumns::historyAction(),
                DeleteAction::make()
                    ->visible($canChange)
                    ->modalDescription('این تأمین‌کننده از SKU برداشته می‌شود و در تاریخچه می‌ماند؛ قیمت فروش دوباره محاسبه می‌شود.')
                    ->using(function (ProviderPrice $record): bool {
                        app(DeleteProviderPrice::class)($record, ProviderPriceColumns::adminId());

                        return true;
                    }),
            ])
            ->emptyStateHeading('هنوز تأمین‌کننده‌ای برای این SKU ثبت نشده است.')
            ->emptyStateDescription('تا وقتی تأمین‌کننده‌ای نیست، این SKU با قیمت روی جعبه فروخته می‌شود. با «افزودن تأمین‌کننده» شروع کنید.');
    }

    private function summary(): ?VariantSummary
    {
        return app(VariantPricingInputs::class)->summary($this->variant ?? (int) request()->route('variant'));
    }

    /**
     * The supplier the website sells from: the active one, as long as there is a commission (without one the SKU is
     * sold at its printed price).
     */
    private function activeSupplier(): ?ProviderPrice
    {
        return $this->summary()?->commissionPercent === null ? null : (app(SupplierPrices::class)->activeFor([$this->variant])[$this->variant] ?? null);
    }

    /**
     * @return list<Field>
     */
    private function priceForm(): array
    {
        return [
            Select::make(ProviderPrice::COLUMN_PROVIDER_ID)
                ->label('تأمین‌کننده')
                ->options(fn (): array => Provider::query()->orderBy(Provider::COLUMN_NAME)->pluck(Provider::COLUMN_NAME, Provider::COLUMN_ID)->all())
                ->searchable()
                ->required()
                ->rules(fn (?ProviderPrice $record): array => StoreProviderPriceRequest::baseRules($this->variant, $record?->getId())[ProviderPrice::COLUMN_PROVIDER_ID]),
            ...ProviderPriceColumns::fields(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function priceData(array $data): ProviderPriceData
    {
        return ProviderPriceData::fromArray([...$data, ProviderPrice::COLUMN_VARIANT_ID => $this->variant]);
    }
}
