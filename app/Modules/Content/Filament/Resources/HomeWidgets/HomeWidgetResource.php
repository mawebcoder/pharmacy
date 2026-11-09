<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\HomeWidgets;

use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Content\Actions\DeleteHomeWidget;
use App\Modules\Content\Actions\SaveHomeWidget;
use App\Modules\Content\Actions\SetPlaceBanners;
use App\Modules\Content\Data\HomeWidgetData;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Exceptions\BannerChangeRefused;
use App\Modules\Content\Exceptions\HomeWidgetChangeRefused;
use App\Modules\Content\Filament\Forms\PlaceBannersField;
use App\Modules\Content\Filament\Resources\HomeWidgets\Pages\ManageHomeWidgets;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\HomeWidgetItems;
use App\Modules\Content\Queries\PlaceBanners;
use App\Modules\Shared\Support\PersianDigits;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * «چیدمان صفحه اصلی» (roadmap D3.6, B9.2, S13b): the admin builds the home page from widgets and drags them into
 * order; each widget shows at most 6 items. Writes go through the Content actions.
 */
final class HomeWidgetResource extends Resource
{
    /**
     * More live widgets than this make the page long (roadmap B2 rule 7); the page warns.
     */
    public const int RECOMMENDED_MAX_WIDGETS = 8;

    protected static ?string $model = HomeWidget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'چیدمان صفحه اصلی';

    protected static ?string $modelLabel = 'ویجت';

    protected static ?string $pluralModelLabel = 'چیدمان صفحه اصلی';

    /**
     * The «در صفحه» column text of a widget the home page shows.
     */
    private const string SHOWN = 'نمایش داده می‌شود';

    public static function form(Schema $schema): Schema
    {
        $options = app(AdminOptions::class);
        $items = fn (string $field, string $type, HomeWidgetType $widgetType, string $label): Select => ImageSelect::images(Select::make($field), $type, fn (): array => $options->names($type))
            ->label($label)
            ->multiple()
            ->maxItems(HomeWidgetType::MAX_ITEMS)
            ->required()
            ->helperText('حداکثر ۶ مورد، به ترتیبی که انتخاب می‌کنید.')
            ->visible(fn (Get $get): bool => self::type($get) === $widgetType);

        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make(HomeWidget::COLUMN_TYPE)
                            ->label('نوع ویجت')
                            ->options(HomeWidgetType::options())
                            ->required()
                            ->live(),
                        TextInput::make(HomeWidget::COLUMN_TITLE_FA)
                            ->label('عنوان')
                            ->maxLength(255)
                            ->helperText('خالی: بدون عنوان (ردیف کالکشن نام کالکشن را نشان می‌دهد).'),
                        ImageSelect::images(Select::make('collection_id'), 'collection', fn (): array => $options->names('collection'))
                            ->label('کالکشن')
                            ->required()
                            ->helperText('۶ محصول اول موجود کالکشن به ترتیب آن، با «مشاهده همه».')
                            ->visible(fn (Get $get): bool => self::type($get) === HomeWidgetType::Collection),
                        $items('brand_ids', 'brand', HomeWidgetType::Brands, 'برندها'),
                        $items('category_ids', 'category', HomeWidgetType::Categories, 'دسته‌بندی‌ها'),
                        $items('tag_ids', 'tag', HomeWidgetType::Tags, 'برچسب‌ها'),
                    ]),
                Section::make('بنرهای اسلایدر')
                    ->visible(fn (Get $get): bool => self::type($get) === HomeWidgetType::Slider)
                    ->schema([PlaceBannersField::make()->minItems(1)]),
                Section::make('نمایش')
                    ->columns(3)
                    ->schema([
                        Toggle::make(HomeWidget::COLUMN_IS_ACTIVE)->label('فعال')->default(true)->inline(false),
                        DateTimePicker::make(HomeWidget::COLUMN_STARTS_AT)->label('شروع نمایش')->helperText('خالی یعنی از همین حالا.'),
                        DateTimePicker::make(HomeWidget::COLUMN_ENDS_AT)->label('پایان نمایش')->helperText('خالی یعنی بدون پایان.')->after(HomeWidget::COLUMN_STARTS_AT),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable(HomeWidget::COLUMN_SORT, fn (): bool => auth('admin')->user()?->can('Update:HomeWidget') ?? false)
            ->defaultSort(HomeWidget::COLUMN_SORT)
            ->paginated(false)
            ->description(function (): string {
                $live = count(app(HomeWidgetItems::class)->shown());
                $text = 'ترتیب ردیف‌ها همان ترتیب صفحه اصلی است؛ با کشیدن جابه‌جا کنید. '.PersianDigits::toPersian($live).' ویجت اکنون نمایش داده می‌شود.';

                return $live > self::RECOMMENDED_MAX_WIDGETS
                    ? $text.' بیش از '.PersianDigits::toPersian(self::RECOMMENDED_MAX_WIDGETS).' ویجت صفحه را طولانی می‌کند.'
                    : $text;
            })
            ->columns([
                TextColumn::make(HomeWidget::COLUMN_TYPE)
                    ->label('نوع')
                    ->icon(fn (HomeWidgetType $state): string => $state->icon())
                    ->formatStateUsing(fn (HomeWidgetType $state): string => $state->label()),
                TextColumn::make(HomeWidget::COLUMN_TITLE_FA)->label('عنوان')->placeholder('—'),
                TextColumn::make('summary')
                    ->label('محتوا')
                    ->state(fn (HomeWidget $record): string => self::summary($record)),
                TextColumn::make('on_page')
                    ->label('در صفحه')
                    ->badge()
                    ->state(fn (HomeWidget $record): string => app(HomeWidgetItems::class)->hiddenReason($record) ?? self::SHOWN)
                    ->color(fn (string $state, HomeWidget $record): string => match (true) {
                        $state === self::SHOWN => 'success',
                        $record->isLive() => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state, HomeWidget $record): ?string => $state !== self::SHOWN && $record->isLive()
                        ? 'ویجتی که موردی برای نمایش ندارد در صفحه اصلی نمایش داده نمی‌شود.'
                        : null),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (HomeWidget $record, array $data): array => self::formData($record, $data))
                    ->using(fn (HomeWidget $record, array $data): HomeWidget => self::save($data, $record)),
                DeleteAction::make()
                    ->modalDescription('ویجت از صفحه اصلی برداشته می‌شود؛ بنرها در کتابخانه می‌مانند.')
                    ->using(function (HomeWidget $record, DeleteHomeWidget $deleteWidget): bool {
                        $deleteWidget($record);

                        return true;
                    }),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('افزودن ویجت')
            ->using(fn (array $data): HomeWidget => self::save($data));
    }

    /**
     * Saves the widget and, for a slider, its banners, in one transaction; refusals are shown and stop the modal.
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data, ?HomeWidget $widget = null): HomeWidget
    {
        try {
            return DB::transaction(function () use ($data, $widget): HomeWidget {
                $saved = app(SaveHomeWidget::class)(HomeWidgetData::fromArray($data), $widget);

                if ($saved->getType() === HomeWidgetType::Slider) {
                    app(SetPlaceBanners::class)(BannerPlace::HomeWidget, $saved->getId(), array_values(array_map(intval(...), (array) ($data['banner_ids'] ?? []))));
                }

                return $saved;
            });
        } catch (HomeWidgetChangeRefused|BannerChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();
            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageHomeWidgets::route('/')];
    }

    /**
     * The edit form's state: the settings at the top level and a slider's banners.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function formData(HomeWidget $widget, array $data): array
    {
        return [
            ...$data,
            ...$widget->getSettings(),
            'banner_ids' => $widget->getType() === HomeWidgetType::Slider ? app(PlaceBanners::class)->ids(BannerPlace::HomeWidget, $widget->getId()) : [],
        ];
    }

    private static function summary(HomeWidget $widget): string
    {
        $count = fn (int $number, string $noun): string => PersianDigits::toPersian($number).' '.$noun;

        return match ($widget->getType()) {
            HomeWidgetType::Slider => $count(count(app(PlaceBanners::class)->ids(BannerPlace::HomeWidget, $widget->getId())), 'بنر'),
            HomeWidgetType::Collection => app(AdminOptions::class)->names('collection')[$widget->getCollectionId() ?? 0] ?? 'کالکشن حذف‌شده',
            HomeWidgetType::Brands => $count(count($widget->getItemIds()), 'برند'),
            HomeWidgetType::Categories => $count(count($widget->getItemIds()), 'دسته'),
            HomeWidgetType::Tags => $count(count($widget->getItemIds()), 'برچسب'),
            HomeWidgetType::Trust => 'مجوزها، داروساز، ارسال، مرجوعی',
        };
    }

    private static function type(Get $get): ?HomeWidgetType
    {
        $value = $get(HomeWidget::COLUMN_TYPE);

        return $value instanceof HomeWidgetType ? $value : HomeWidgetType::tryFrom((string) $value);
    }
}
