<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Livewire;

use App\Modules\Pricing\Actions\DeleteBrandProvider;
use App\Modules\Pricing\Actions\SaveBrandProvider;
use App\Modules\Pricing\Data\BrandProviderData;
use App\Modules\Pricing\Filament\Support\ProviderPriceColumns;
use App\Modules\Pricing\Models\BrandProvider;
use App\Modules\Pricing\Models\Provider;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
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
 * «کمیسیون به تفکیک تأمین‌کننده» on the brand form (roadmap D3.2, S15b, level 4): a supplier's commission for this
 * brand, over the brand's own and under any product commission. Changes re-price the brand's SKUs that buy from that
 * supplier in the background.
 */
final class BrandProvidersTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $brand;

    public function table(Table $table): Table
    {
        $canChange = ProviderPriceColumns::canChange(...);

        return $table
            ->heading('کمیسیون به تفکیک تأمین‌کننده')
            ->description('اگر یک تأمین‌کننده برای این برند کمیسیون دیگری دارد، اینجا تعیین کنید. برای محصولات بدون کمیسیون اختصاصی، به جای کمیسیون برند به کار می‌رود.')
            ->query(fn (): Builder => BrandProvider::query()->with('provider')->where(BrandProvider::COLUMN_BRAND_ID, $this->brand))
            ->paginated(false)
            ->columns([
                TextColumn::make('provider.name')->label('تأمین‌کننده'),
                TextColumn::make(BrandProvider::COLUMN_COMMISSION_PERCENT)
                    ->label('کمیسیون')
                    ->formatStateUsing(fn (float|string $state): ?string => ProviderPriceColumns::percent((float) $state)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن تأمین‌کننده')
                    ->visible($canChange)
                    ->schema($this->fields())
                    ->using(fn (array $data): BrandProvider => app(SaveBrandProvider::class)($this->data($data), adminId: ProviderPriceColumns::adminId())),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible($canChange)
                    ->schema($this->fields())
                    ->using(fn (BrandProvider $record, array $data): BrandProvider => app(SaveBrandProvider::class)($this->data($data), $record, ProviderPriceColumns::adminId())),
                DeleteAction::make()
                    ->visible($canChange)
                    ->using(function (BrandProvider $record): bool {
                        app(DeleteBrandProvider::class)($record, ProviderPriceColumns::adminId());

                        return true;
                    }),
            ])
            ->emptyStateHeading('همه تأمین‌کنندگان با کمیسیون خود برند.');
    }

    public function render(): View
    {
        return view('pricing::livewire.table');
    }

    /**
     * @return list<Select|TextInput>
     */
    private function fields(): array
    {
        return [
            Select::make(BrandProvider::COLUMN_PROVIDER_ID)
                ->label('تأمین‌کننده')
                ->options(fn (): array => Provider::query()->orderBy(Provider::COLUMN_NAME)->pluck(Provider::COLUMN_NAME, Provider::COLUMN_ID)->all())
                ->searchable()
                ->required()
                ->rules(fn (?BrandProvider $record): array => [Rule::unique(BrandProvider::class, BrandProvider::COLUMN_PROVIDER_ID)->where(BrandProvider::COLUMN_BRAND_ID, $this->brand)->ignore($record?->getId())]),
            TextInput::make(BrandProvider::COLUMN_COMMISSION_PERCENT)->label('کمیسیون')->numeric()->minValue(0)->maxValue(100)->suffix('٪')->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function data(array $data): BrandProviderData
    {
        return new BrandProviderData(
            brandId: $this->brand,
            providerId: (int) $data[BrandProvider::COLUMN_PROVIDER_ID],
            commissionPercent: (float) $data[BrandProvider::COLUMN_COMMISSION_PERCENT],
        );
    }
}
