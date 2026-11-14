<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Pages;

use App\Modules\Content\Filament\Resources\Pages\Pages\CreatePage;
use App\Modules\Content\Filament\Resources\Pages\Pages\EditPage;
use App\Modules\Content\Filament\Resources\Pages\Pages\ListPages;
use App\Modules\Content\Http\Requests\SavePageRequest;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Support\PageContent;
use App\Modules\Shared\Support\PersianSlug;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Static pages («صفحه‌های ثابت», roadmap D3.6, S34): title, address under /pages/, rich-editor body (images on the
 * default filesystem disk), active switch and SEO fields. Banners can link to a page.
 */
final class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'صفحه ثابت';

    protected static ?string $pluralModelLabel = 'صفحه‌های ثابت';

    protected static ?string $recordTitleAttribute = Page::COLUMN_TITLE;

    public static function form(Schema $schema): Schema
    {
        $rules = static fn (string $column, ?Model $record): array => SavePageRequest::baseRules($record instanceof Page ? $record->getId() : null)[$column];

        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Page::COLUMN_TITLE)
                            ->label('عنوان')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create' && blank($get(Page::COLUMN_SLUG))) {
                                    $set(Page::COLUMN_SLUG, PersianSlug::make((string) $state));
                                }
                            }),
                        TextInput::make(Page::COLUMN_SLUG)
                            ->label('آدرس صفحه')
                            ->prefix('/pages/')
                            ->helperText('حروف کوچک انگلیسی، فارسی یا عدد، جدا با «-». با تغییر آدرس، بنرها خودکار به آدرس جدید می‌روند.')
                            ->required()
                            ->maxLength(120)
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->dehydrateStateUsing(fn (?string $state): string => mb_strtolower(trim((string) $state)))
                            ->rules(fn (?Model $record): array => $rules(Page::COLUMN_SLUG, $record))
                            ->validationMessages(['regex' => 'آدرس فقط حروف، عدد و «-» دارد (مثلاً shipping).', 'unique' => 'این آدرس برای صفحه دیگری است.']),
                        Toggle::make(Page::COLUMN_IS_ACTIVE)
                            ->label('منتشر شود')
                            ->helperText('صفحه خاموش برای مشتری‌ها پیدا نمی‌شود و بنرهای آن نمایش داده نمی‌شوند.')
                            ->default(false),
                    ]),
                Section::make('متن صفحه')
                    ->schema([
                        RichEditor::make(Page::COLUMN_BODY)
                            ->hiddenLabel()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3'],
                                ['alignStart', 'alignCenter', 'alignEnd'],
                                ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
                                ['table', 'attachFiles'],
                                ['undo', 'redo'],
                            ])
                            ->fileAttachmentsDisk(PageContent::disk())
                            ->fileAttachmentsDirectory(Page::ATTACHMENTS_DIRECTORY)
                            ->fileAttachmentsVisibility('private')
                            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->fileAttachmentsMaxSize(2048)
                            ->extraInputAttributes(['style' => 'min-height: 24rem']),
                    ]),
                Section::make('سئو')
                    ->description('خالی بماند تا عنوان صفحه استفاده شود.')
                    ->collapsed()
                    ->columns(1)
                    ->schema([
                        TextInput::make(Page::COLUMN_SEO_TITLE)->label('عنوان در گوگل')->maxLength(120),
                        Textarea::make(Page::COLUMN_SEO_DESCRIPTION)->label('توضیح در گوگل')->rows(2)->maxLength(300),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(Page::COLUMN_TITLE)->label('عنوان')->searchable()->sortable(),
                TextColumn::make(Page::COLUMN_SLUG)
                    ->label('آدرس')
                    ->formatStateUsing(fn (string $state): string => '/pages/'.$state)
                    ->url(fn (Page $record): ?string => $record->isActive() && ! $record->trashed() ? $record->url() : null, shouldOpenInNewTab: true)
                    ->extraAttributes(['dir' => 'ltr'])
                    ->copyable()
                    ->copyableState(fn (Page $record): string => $record->url())
                    ->searchable(),
                IconColumn::make(Page::COLUMN_IS_ACTIVE)->label('منتشرشده')->boolean(),
                TextColumn::make(Page::COLUMN_UPDATED_AT)
                    ->label('آخرین ویرایش')
                    ->jalaliDateTime() // @phpstan-ignore method.notFound (macro from ariaieboy/filament-jalali)
                    ->sortable(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()])
            ->defaultSort(Page::COLUMN_TITLE);
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
