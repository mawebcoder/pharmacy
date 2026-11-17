<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Faqs;

use App\Modules\Content\Actions\DeleteFaq;
use App\Modules\Content\Actions\SaveFaq;
use App\Modules\Content\Data\FaqData;
use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Modules\Content\Http\Requests\SaveFaqRequest;
use App\Modules\Content\Models\Faq;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use UnitEnum;

/**
 * «پرسش‌های متداول» (roadmap D3.6, S34): questions in a fixed list of groups, with plain-text answers, dragged into
 * order; /faq shows the active ones by group. Writes go through the Content actions.
 */
final class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'پرسش';

    protected static ?string $pluralModelLabel = 'پرسش‌های متداول';

    public static function form(Schema $schema): Schema
    {
        $rules = SaveFaqRequest::baseRules();

        return $schema
            ->columns(2)
            ->components([
                Select::make(Faq::COLUMN_GROUP)->label('گروه')->options(FaqGroup::options())->required()->rules($rules[Faq::COLUMN_GROUP]),
                Toggle::make(Faq::COLUMN_IS_ACTIVE)->label('نمایش در سایت')->default(true)->inline(false),
                TextInput::make(Faq::COLUMN_QUESTION)->label('پرسش')->required()->maxLength(255)->columnSpanFull(),
                Textarea::make(Faq::COLUMN_ANSWER)
                    ->label('پاسخ')
                    ->required()
                    ->maxLength(3000)
                    ->rows(5)
                    ->helperText('متن ساده؛ هر خط جدید در سایت هم خط جدید است.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable(Faq::COLUMN_SORT, fn (): bool => auth('admin')->user()?->can('Update:Faq') ?? false)
            ->defaultSort(Faq::COLUMN_SORT)
            ->defaultGroup(Group::make(Faq::COLUMN_GROUP)->label('گروه')->getTitleFromRecordUsing(fn (Faq $record): string => $record->getGroup()->label()))
            ->paginated(false)
            ->description('در هر گروه، ترتیب ردیف‌ها همان ترتیب صفحه «پرسش‌های متداول» است؛ با کشیدن جابه‌جا کنید.')
            ->columns([
                TextColumn::make(Faq::COLUMN_QUESTION)->label('پرسش')->searchable()->wrap(),
                IconColumn::make(Faq::COLUMN_IS_ACTIVE)->label('نمایش')->boolean(),
            ])
            ->filters([
                SelectFilter::make(Faq::COLUMN_GROUP)->label('گروه')->options(FaqGroup::options()),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Faq $record, array $data): Faq => app(SaveFaq::class)(FaqData::fromArray($data), $record)),
                DeleteAction::make()
                    ->using(function (Faq $record, DeleteFaq $deleteFaq): bool {
                        $deleteFaq($record);

                        return true;
                    }),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('افزودن پرسش')
            ->using(fn (array $data): Faq => app(SaveFaq::class)(FaqData::fromArray($data)));
    }

    public static function getPages(): array
    {
        return ['index' => ManageFaqs::route('/')];
    }
}
