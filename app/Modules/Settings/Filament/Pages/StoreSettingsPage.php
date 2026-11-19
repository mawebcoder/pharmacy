<?php

declare(strict_types=1);

namespace App\Modules\Settings\Filament\Pages;

use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Locations\Queries\CitySearch;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Enums\SocialPlatform;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shared\Rules\IranianMobile;
use App\Modules\Shared\Support\IranMobile;
use App\Modules\Shipping\Enums\TipaxReferenceType;
use App\Modules\Shipping\Queries\TipaxReferences;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * «تنظیمات فروشگاه» (roadmap D3.6, started in S21; S30 adds the contact details and social links). «جستجو» pins
 * categories and brands to the search panel (owner, 2026-11-15).
 *
 * @property-read Schema $form
 */
final class StoreSettingsPage extends Page
{
    use HasPageShield;

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'تنظیمات فروشگاه';

    protected static ?string $title = 'تنظیمات فروشگاه';

    protected static ?string $slug = 'store-settings';

    public function mount(StoreSettings $settings): void
    {
        $this->form->fill([
            'max_quantity_per_order' => $settings->maxQuantityPerOrder(),
            'free_shipping_threshold' => $settings->freeShippingThreshold(),
            'removal_delay_seconds' => $settings->removalDelaySeconds(),
            'shipping_flat_rate' => $settings->shippingFlatRate(),
            'shipping_origin_city_id' => $settings->shippingOriginCityId(),
            'shipping_markup_percent' => $settings->shippingMarkupPercent(),
            'tipax_pack_type' => $settings->tipaxCode(StoreSettings::TIPAX_PACK_TYPE) ?? (int) config('shipping.tipax.pricing.pack_type'),
            'tipax_packing_id' => $settings->tipaxCode(StoreSettings::TIPAX_PACKING_ID) ?? (int) config('shipping.tipax.pricing.packing_id'),
            'tipax_content_general_id' => $settings->tipaxCode(StoreSettings::TIPAX_CONTENT_GENERAL_ID) ?? (int) config('shipping.tipax.pricing.content_general_id'),
            'tipax_content_liquid_id' => $settings->tipaxCode(StoreSettings::TIPAX_CONTENT_LIQUID_ID) ?? (int) config('shipping.tipax.pricing.content_liquid_id'),
            'whatsapp_number' => $settings->whatsAppNumber(),
            'telegram_username' => $settings->telegramUsername(),
            'chat_message_product' => $settings->get(StoreSettings::CHAT_MESSAGE_PRODUCT),
            'chat_message_general' => $settings->generalChatMessage(),
            'phone' => $settings->phone(),
            'email' => $settings->email(),
            'address' => $settings->address(),
            'working_hours' => $settings->workingHours(),
            'social_links' => array_map(fn (array $link): array => ['platform' => $link['platform']->value, 'url' => $link['url']], $settings->socialLinks()),
            'search_category_ids' => $settings->searchCategoryIds(),
            'search_brand_ids' => $settings->searchBrandIds(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('سبد خرید')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_quantity_per_order')
                            ->label('حداکثر تعداد هر کالا در یک سفارش')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(999)
                            ->required()
                            ->helperText('وقتی نه تنوع (SKU) و نه محصول حداکثری تعیین کرده باشد، همین عدد اعمال می‌شود.'),
                        TextInput::make('free_shipping_threshold')
                            ->label('حد ارسال رایگان')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required()
                            ->helperText('سفارش‌های از این مبلغ به بالا رایگان ارسال می‌شوند؛ صفر یعنی بدون ارسال رایگان. در سبد خرید و نوار اطلاعیه نمایش داده می‌شود.'),
                        TextInput::make('removal_delay_seconds')
                            ->label('مهلت انصراف از حذف کالا (ثانیه)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(StoreSettings::MAX_REMOVAL_DELAY_SECONDS)
                            ->required()
                            ->helperText('با زدن سطل زباله، کالا پس از این چند ثانیه حذف می‌شود و تا آن زمان شمارش معکوس و دکمه «انصراف» نمایش داده می‌شود؛ صفر یعنی حذف فوری.'),
                    ]),
                Section::make('ارسال')
                    ->columns(2)
                    ->schema([
                        Select::make('shipping_origin_city_id')
                            ->label('شهر مبدأ ارسال')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => app(CitySearch::class)->options($search))
                            ->getOptionLabelUsing(fn (int|string $value): ?string => app(CitySearch::class)->label((int) $value))
                            ->helperText('شهر انبار که بسته‌ها از آن ارسال می‌شوند؛ تا انتخاب نشود، هزینه ارسال از تیپاکس گرفته نمی‌شود و «هزینه ارسال ثابت» حساب می‌شود.'),
                        TextInput::make('shipping_markup_percent')
                            ->label('افزایش قیمت ارسال (درصد)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(StoreSettings::MAX_SHIPPING_MARKUP_PERCENT)
                            ->required()
                            ->helperText('این درصد به قیمت تیپاکس اضافه می‌شود، مثلاً برای اختلاف با قیمت باجه؛ صفر یعنی همان قیمت تیپاکس.'),
                        TextInput::make('shipping_flat_rate')
                            ->label('هزینه ارسال ثابت')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required()
                            ->helperText('وقتی تیپاکس پاسخ ندهد یا شهر مبدأ انتخاب نشده باشد، همین مبلغ حساب می‌شود؛ بالای حد ارسال رایگان یا با کوپن ارسال رایگان، رایگان است.'),
                    ]),
                Section::make('کدهای تیپاکس')
                    ->description('با هر بسته برای استعلام قیمت به تیپاکس فرستاده می‌شوند. فهرست‌ها هر شب از تیپاکس به‌روز می‌شوند (php artisan tipax:sync-reference)؛ بسته‌بندی و محتوا باید از همان نوع بسته باشند.')
                    ->columns(2)
                    ->schema([
                        Select::make('tipax_pack_type')
                            ->label('نوع بسته')
                            ->options(fn (): array => self::tipaxOptions(TipaxReferenceType::PackType, null, 'pack_type'))
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('tipax_packing_id', null);
                                $set('tipax_content_general_id', null);
                                $set('tipax_content_liquid_id', null);
                            })
                            ->required(),
                        Select::make('tipax_packing_id')
                            ->label('بسته‌بندی')
                            ->options(fn (Get $get): array => self::tipaxOptions(TipaxReferenceType::Packing, (int) $get('tipax_pack_type'), 'packing_id'))
                            ->helperText('ما خودمان بسته‌بندی می‌کنیم: «نیاز به بسته بندی ندارد».')
                            ->required(),
                        Select::make('tipax_content_general_id')
                            ->label('محتوای بسته')
                            ->options(fn (Get $get): array => self::tipaxOptions(TipaxReferenceType::Content, (int) $get('tipax_pack_type'), 'content_general_id'))
                            ->required(),
                        Select::make('tipax_content_liquid_id')
                            ->label('محتوای بسته با کالای مایع یا شکستنی')
                            ->options(fn (Get $get): array => self::tipaxOptions(TipaxReferenceType::Content, (int) $get('tipax_pack_type'), 'content_liquid_id'))
                            ->required(),
                    ]),
                Section::make('اطلاعات تماس')
                    ->description('در پانویس همه صفحه‌ها و صفحه «تماس با ما» نمایش داده می‌شوند؛ خالی یعنی نمایش داده نمی‌شود.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone')
                            ->label('تلفن')
                            ->maxLength(30)
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'tel'])
                            ->placeholder('021-12345678')
                            ->regex('/^[0-9۰-۹+\-\s()]+$/u')
                            ->validationMessages(['regex' => 'تلفن فقط عدد، فاصله، «-» و «+» دارد.']),
                        TextInput::make('email')->label('ایمیل')->email()->maxLength(120)->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('address')->label('نشانی')->rows(2)->maxLength(300),
                        TextInput::make('working_hours')->label('ساعت پاسخ‌گویی')->maxLength(120)->placeholder('شنبه تا پنجشنبه، ۹ تا ۱۷'),
                    ]),
                Section::make('شبکه‌های اجتماعی')
                    ->description('به ترتیب این فهرست با آیکن در پانویس نمایش داده می‌شوند؛ با کشیدن جابه‌جا کنید.')
                    ->schema([
                        Repeater::make('social_links')
                            ->hiddenLabel()
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->maxItems(10)
                            ->addActionLabel('افزودن شبکه اجتماعی')
                            ->defaultItems(0)
                            ->schema([
                                Select::make('platform')->label('شبکه')->options(SocialPlatform::options())->required()->distinct(),
                                TextInput::make('url')
                                    ->label('آدرس')
                                    ->url()
                                    ->startsWith(['https://'])
                                    ->maxLength(300)
                                    ->required()
                                    ->extraInputAttributes(['dir' => 'ltr'])
                                    ->placeholder('https://instagram.com/…'),
                            ]),
                    ]),
                Section::make('تماس')
                    ->columns(2)
                    ->schema([
                        TextInput::make('whatsapp_number')
                            ->label('شماره واتساپ پشتیبانی')
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'tel', 'autocomplete' => 'tel'])
                            ->placeholder('09123456789')
                            ->rule(new IranianMobile)
                            ->helperText('«مشاوره با داروساز» (سربرگ سایت و صفحه محصول) گفت‌وگوی واتساپ را با این شماره باز می‌کند؛ خالی یعنی بدون واتساپ.'),
                        TextInput::make('telegram_username')
                            ->label('نام کاربری تلگرام پشتیبانی')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->prefix('@')
                            ->placeholder('pharmacy_support')
                            ->dehydrateStateUsing(fn (?string $state): string => self::telegramUsername($state))
                            ->rule('nullable')
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                if (filled($value) && preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/', self::telegramUsername((string) $value)) !== 1) {
                                    $fail('نام کاربری تلگرام ۵ تا ۳۲ حرف انگلیسی، عدد یا _ است و با حرف شروع می‌شود.');
                                }
                            })
                            ->helperText('مثلاً pharmacy_support یا لینک t.me؛ «مشاوره با داروساز» تلگرام را هم پیشنهاد می‌دهد. خالی یعنی بدون تلگرام.'),
                        Textarea::make('chat_message_product')
                            ->label('پیام پیش‌فرض از صفحه محصول')
                            ->rows(2)
                            ->maxLength(500)
                            ->required()
                            ->helperText('در گفت‌وگو نوشته می‌شود تا مشتری بفرستد. {product} نام محصول، {variant} نوع انتخاب‌شده (در پرانتز؛ اگر نوعی نباشد حذف می‌شود) و {link} لینک صفحه محصول.'),
                        Textarea::make('chat_message_general')
                            ->label('پیام پیش‌فرض عمومی')
                            ->rows(2)
                            ->maxLength(500)
                            ->required()
                            ->helperText('برای «مشاوره با داروساز» در سربرگ سایت، جایی که محصولی انتخاب نشده است.'),
                    ]),
                Section::make('جستجو')
                    ->description('در پنل جستجوی سایت وقتی کادر خالی است، یا متن جستجو با هیچ دسته‌بندی یا برندی جور نمی‌شود، نمایش داده می‌شوند.')
                    ->columns(2)
                    ->schema([
                        self::shortcutPicker('search_category_ids', 'category', 'دسته‌بندی‌های ثابت در جستجو'),
                        self::shortcutPicker('search_brand_ids', 'brand', 'برندهای ثابت در جستجو'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('ذخیره')->submit('save')])->key('form-actions'),
                ]),
        ]);
    }

    public function save(SaveStoreSettings $saveSettings): void
    {
        $data = $this->form->getState();

        $saveSettings([
            StoreSettings::MAX_QUANTITY_PER_ORDER => (int) $data['max_quantity_per_order'],
            StoreSettings::FREE_SHIPPING_THRESHOLD => (int) $data['free_shipping_threshold'],
            StoreSettings::REMOVAL_DELAY_SECONDS => (int) $data['removal_delay_seconds'],
            StoreSettings::SHIPPING_FLAT_RATE => (int) $data['shipping_flat_rate'],
            StoreSettings::SHIPPING_ORIGIN_CITY_ID => (int) ($data['shipping_origin_city_id'] ?? 0),
            StoreSettings::SHIPPING_MARKUP_PERCENT => (int) $data['shipping_markup_percent'],
            StoreSettings::TIPAX_PACK_TYPE => (int) $data['tipax_pack_type'],
            StoreSettings::TIPAX_PACKING_ID => (int) $data['tipax_packing_id'],
            StoreSettings::TIPAX_CONTENT_GENERAL_ID => (int) $data['tipax_content_general_id'],
            StoreSettings::TIPAX_CONTENT_LIQUID_ID => (int) $data['tipax_content_liquid_id'],
            StoreSettings::WHATSAPP_NUMBER => IranMobile::normalize((string) ($data['whatsapp_number'] ?? '')) ?? '',
            StoreSettings::TELEGRAM_USERNAME => (string) ($data['telegram_username'] ?? ''),
            StoreSettings::CHAT_MESSAGE_PRODUCT => trim((string) $data['chat_message_product']),
            StoreSettings::CHAT_MESSAGE_GENERAL => trim((string) $data['chat_message_general']),
            StoreSettings::PHONE => trim((string) ($data['phone'] ?? '')),
            StoreSettings::EMAIL => mb_strtolower(trim((string) ($data['email'] ?? ''))),
            StoreSettings::ADDRESS => trim(strip_tags((string) ($data['address'] ?? ''))),
            StoreSettings::WORKING_HOURS => trim(strip_tags((string) ($data['working_hours'] ?? ''))),
            StoreSettings::SOCIAL_LINKS => array_values(array_map(
                fn (array $link): array => ['platform' => (string) $link['platform'], 'url' => trim((string) $link['url'])],
                (array) ($data['social_links'] ?? []),
            )),
            StoreSettings::SEARCH_CATEGORY_IDS => array_values(array_map(intval(...), (array) ($data['search_category_ids'] ?? []))),
            StoreSettings::SEARCH_BRAND_IDS => array_values(array_map(intval(...), (array) ($data['search_brand_ids'] ?? []))),
        ]);

        Notification::make()->success()->title('تنظیمات ذخیره شد.')->send();
    }

    /**
     * A Tipax list for a dropdown (S33); before the first `tipax:sync-reference` only the .env default
     * (shipping.tipax.pricing.{$defaultKey}), so the page can always be saved.
     *
     * @return array<int, string>
     */
    private static function tipaxOptions(TipaxReferenceType $type, ?int $packType, string $defaultKey): array
    {
        $options = app(TipaxReferences::class)->options($type, $packType);
        $default = (int) config('shipping.tipax.pricing.'.$defaultKey);

        return $options !== [] ? $options : [$default => 'پیش‌فرض ('.$default.')'];
    }

    /**
     * Pinned categories or brands for the search panel (owner, 2026-11-15): round images, in the order picked.
     */
    private static function shortcutPicker(string $field, string $type, string $label): Select
    {
        $options = app(AdminOptions::class);

        return ImageSelect::images(Select::make($field), $type, fn (): array => $options->names($type))
            ->label($label)
            ->multiple()
            ->maxItems(StoreSettings::MAX_SEARCH_SHORTCUTS)
            ->helperText('حداکثر ۸ مورد، به ترتیبی که انتخاب می‌کنید؛ موارد غیرفعال نمایش داده نمی‌شوند.');
    }

    /**
     * «@name», «t.me/name» or «https://t.me/name» → «name».
     */
    private static function telegramUsername(?string $input): string
    {
        return (string) preg_replace('~^(?:https?://)?(?:t\.me/|telegram\.me/)?@?~i', '', trim((string) $input));
    }
}
