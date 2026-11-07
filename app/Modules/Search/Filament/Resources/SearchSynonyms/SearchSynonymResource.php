<?php

declare(strict_types=1);

namespace App\Modules\Search\Filament\Resources\SearchSynonyms;

use App\Modules\Search\Actions\DeleteSearchSynonym;
use App\Modules\Search\Actions\SaveSearchSynonym;
use App\Modules\Search\Filament\Resources\SearchSynonyms\Pages\ManageSearchSynonyms;
use App\Modules\Search\Models\SearchSynonym;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Search synonyms (roadmap D21.7): a word or phrase and the ones that mean the same; searching any of them finds
 * the others. Saved to the search index automatically.
 */
final class SearchSynonymResource extends Resource
{
    protected static ?string $model = SearchSynonym::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'مترادف جستجو';

    protected static ?string $pluralModelLabel = 'مترادف‌های جستجو';

    protected static ?string $recordTitleAttribute = SearchSynonym::COLUMN_TERM;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make(SearchSynonym::COLUMN_TERM)
                    ->label('عبارت')
                    ->placeholder('مثلاً ویتامین دی')
                    ->required()
                    ->maxLength(255)
                    ->unique(SearchSynonym::class, SearchSynonym::COLUMN_TERM, ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule),
                TagsInput::make(SearchSynonym::COLUMN_SYNONYMS)
                    ->label('هم‌معنی‌ها')
                    ->placeholder('مثلاً d3 یا vitamin d؛ Enter بزنید')
                    ->required()
                    ->helperText('جستجوی هر کدام، نتایج بقیه را هم نشان می‌دهد.')
                    ->nestedRecursiveRules(['string', 'max:255']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(SearchSynonym::COLUMN_TERM)->label('عبارت')->searchable()->sortable(),
                TextColumn::make(SearchSynonym::COLUMN_SYNONYMS)->label('هم‌معنی‌ها')->badge()->separator(','),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (SearchSynonym $record, array $data, SaveSearchSynonym $saveSynonym): SearchSynonym => $saveSynonym(
                        (string) $data[SearchSynonym::COLUMN_TERM],
                        array_values(array_map(strval(...), (array) $data[SearchSynonym::COLUMN_SYNONYMS])),
                        $record,
                    )),
                DeleteAction::make()
                    ->using(function (SearchSynonym $record, DeleteSearchSynonym $deleteSynonym): bool {
                        $deleteSynonym($record);

                        return true;
                    }),
            ])
            ->defaultSort(SearchSynonym::COLUMN_TERM);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSearchSynonyms::route('/'),
        ];
    }
}
