<?php

declare(strict_types=1);

namespace App\Modules\Search\Filament\Pages;

use App\Modules\Search\Actions\SaveSearchSynonym;
use App\Modules\Search\Models\SearchLog;
use App\Modules\Shared\Support\PersianDigits;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Search insights (roadmap D21.7): what shoppers search for, grouped by normalised text — top searches,
 * searches with no results (add a synonym in one click) and searches nobody opened a product from.
 */
final class SearchInsights extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'گزارش جستجو';

    protected static ?string $title = 'گزارش جستجو';

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SearchLog::query()
                ->selectRaw('MIN(id) as id, normalized_query, MAX(query) as query, COUNT(*) as searches')
                ->selectRaw('SUM(results_count = 0) as zero_results, SUM(clicked_product_id IS NOT NULL) as clicks, MAX(created_at) as last_searched_at')
                ->groupBy(SearchLog::COLUMN_NORMALIZED_QUERY))
            ->modifyQueryUsing(fn (Builder $query): Builder => match ($this->getTableFilterState('kind')['value'] ?? 'zero') {
                'zero' => $query->havingRaw('SUM(results_count = 0) > 0'),
                'no_click' => $query->havingRaw('SUM(results_count > 0) > 0 AND SUM(clicked_product_id IS NOT NULL) = 0'),
                default => $query,
            })
            ->columns([
                TextColumn::make(SearchLog::COLUMN_QUERY)->label('عبارت')->searchable(query: fn (Builder $query, string $search): Builder => $query->where(SearchLog::COLUMN_NORMALIZED_QUERY, 'like', '%'.$search.'%')),
                TextColumn::make('searches')->label('تعداد جستجو')->sortable()->formatStateUsing(fn (int|string $state): string => PersianDigits::toPersian((int) $state)),
                TextColumn::make('zero_results')->label('بی‌نتیجه')->sortable()->color(fn (int|string $state): ?string => (int) $state > 0 ? 'danger' : null)->formatStateUsing(fn (int|string $state): string => PersianDigits::toPersian((int) $state)),
                TextColumn::make('clicks')->label('کلیک')->sortable()->formatStateUsing(fn (int|string $state): string => PersianDigits::toPersian((int) $state)),
                TextColumn::make('last_searched_at')->label('آخرین جستجو')->jalaliDateTime()->sortable(), // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('نوع')
                    ->options(['zero' => 'بدون نتیجه', 'no_click' => 'بدون کلیک', 'all' => 'همه'])
                    ->default('zero')
                    // Applied as HAVING in modifyQueryUsing(): filter queries are wrapped in a WHERE group.
                    ->query(fn (Builder $query): Builder => $query),
                SelectFilter::make('period')
                    ->label('بازه')
                    ->options(['7' => '۷ روز اخیر', '30' => '۳۰ روز اخیر', '90' => '۹۰ روز اخیر'])
                    ->default('30')
                    ->query(fn (Builder $query, array $data): Builder => $query->where(SearchLog::COLUMN_CREATED_AT, '>=', now()->subDays((int) ($data['value'] ?? 30)))),
            ])
            ->recordActions([
                Action::make('addSynonym')
                    ->label('افزودن مترادف')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => auth('admin')->user()?->can('Create:SearchSynonym') ?? false)
                    ->fillForm(fn (SearchLog $record): array => ['term' => $record->getQuery()])
                    ->schema([
                        TextInput::make('term')->label('عبارت جستجوشده')->required()->maxLength(255),
                        TagsInput::make('synonyms')->label('هم‌معنی‌ها')->required()->helperText('مثلاً نام درست یا نام انگلیسی کالایی که مشتری دنبالش بوده است.'),
                    ])
                    ->action(function (array $data, SaveSearchSynonym $saveSynonym): void {
                        $synonym = $saveSynonym((string) $data['term'], array_values(array_map(strval(...), (array) $data['synonyms'])));
                        Notification::make()->success()->title('مترادف «'.$synonym->getTerm().'» ذخیره شد.')->send();
                    }),
            ])
            ->defaultSort('searches', 'desc')
            // Rows are groups, so there is no row id to add as a tie-breaker sort.
            ->defaultKeySort(false)
            ->emptyStateHeading('جستجویی ثبت نشده است.');
    }
}
