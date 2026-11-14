<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Livewire;

use App\Modules\Pricing\Actions\DeleteProductProvider;
use App\Modules\Pricing\Actions\SaveProductProvider;
use App\Modules\Pricing\Data\ProductProviderData;
use App\Modules\Pricing\Filament\Support\ProviderPriceColumns;
use App\Modules\Pricing\Models\ProductProvider;
use App\Modules\Pricing\Models\Provider;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * «تأمین‌کنندگان محصول» on the product form (roadmap D3.2, S15b): per supplier, its commission for this product
 * (level 2, over the product's own) and whether it is the product's preferred supplier (for every SKU that has no
 * preferred supplier of its own). Changes re-price the product's SKUs in the background.
 */
final class ProductProvidersTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $product;

    public function table(Table $table): Table
    {
        $canChange = ProviderPriceColumns::canChange(...);

        return $table
            ->heading('تأمین‌کنندگان محصول')
            ->description('کمیسیون هر تأمین‌کننده برای این محصول (بر کمیسیون محصول مقدم است؛ کمیسیون ردیف SKU بر این مقدم است) و تأمین‌کننده انتخابی محصول (برای SKUهایی که انتخابی خودشان را ندارند). سایت تا وقتی تأمین‌کننده انتخابی قابل فروش است از او می‌فروشد، وگرنه از کمترین قیمت فروش.')
            ->query(fn (): Builder => ProductProvider::query()->with('provider')->where(ProductProvider::COLUMN_PRODUCT_ID, $this->product))
            ->paginated(false)
            ->columns([
                TextColumn::make('provider.name')->label('تأمین‌کننده'),
                TextColumn::make(ProductProvider::COLUMN_COMMISSION_PERCENT)
                    ->label('کمیسیون برای این محصول')
                    ->formatStateUsing(fn (float|string|null $state): ?string => ProviderPriceColumns::percent($state === null ? null : (float) $state))
                    ->placeholder('کمیسیون محصول یا برند'),
                IconColumn::make(ProductProvider::COLUMN_IS_PREFERRED)->label('تأمین‌کننده انتخابی')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن تأمین‌کننده')
                    ->visible($canChange)
                    ->schema($this->fields())
                    ->using(fn (array $data): ProductProvider => app(SaveProductProvider::class)($this->data($data), adminId: ProviderPriceColumns::adminId())),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible($canChange)
                    ->schema($this->fields())
                    ->using(fn (ProductProvider $record, array $data): ProductProvider => app(SaveProductProvider::class)($this->data($data), $record, ProviderPriceColumns::adminId())),
                DeleteAction::make()
                    ->visible($canChange)
                    ->using(function (ProductProvider $record): bool {
                        app(DeleteProductProvider::class)($record, ProviderPriceColumns::adminId());

                        return true;
                    }),
            ])
            ->emptyStateHeading('تأمین‌کننده‌ای برای این محصول تنظیم نشده است.')
            ->emptyStateDescription('فقط برای کمیسیون جداگانه یا تأمین‌کننده انتخابی لازم است؛ قیمت خرید هر SKU در صفحه «تأمین‌کنندگان» همان SKU ثبت می‌شود.');
    }

    public function render(): View
    {
        return view('pricing::livewire.table');
    }

    /**
     * @return list<Select|TextInput|Toggle>
     */
    private function fields(): array
    {
        return [
            Select::make(ProductProvider::COLUMN_PROVIDER_ID)
                ->label('تأمین‌کننده')
                ->options(fn (): array => Provider::query()->orderBy(Provider::COLUMN_NAME)->pluck(Provider::COLUMN_NAME, Provider::COLUMN_ID)->all())
                ->searchable()
                ->required()
                ->rules(fn (?ProductProvider $record): array => [Rule::unique(ProductProvider::class, ProductProvider::COLUMN_PROVIDER_ID)->where(ProductProvider::COLUMN_PRODUCT_ID, $this->product)->ignore($record?->getId())]),
            TextInput::make(ProductProvider::COLUMN_COMMISSION_PERCENT)
                ->label('کمیسیون برای این محصول')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('٪')
                ->helperText('اختیاری. خالی: کمیسیون محصول.'),
            Toggle::make(ProductProvider::COLUMN_IS_PREFERRED)
                ->label('تأمین‌کننده انتخابی محصول')
                ->helperText('انتخابی قبلی این محصول خاموش می‌شود.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function data(array $data): ProductProviderData
    {
        return new ProductProviderData(
            productId: $this->product,
            providerId: (int) $data[ProductProvider::COLUMN_PROVIDER_ID],
            commissionPercent: blank($data[ProductProvider::COLUMN_COMMISSION_PERCENT] ?? null) ? null : (float) $data[ProductProvider::COLUMN_COMMISSION_PERCENT],
            isPreferred: (bool) ($data[ProductProvider::COLUMN_IS_PREFERRED] ?? false),
        );
    }
}
