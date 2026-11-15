<?php

declare(strict_types=1);

namespace App\Modules\Settings\Filament\Pages;

use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shared\Rules\IranianMobile;
use App\Modules\Shared\Support\IranMobile;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * «تنظیمات فروشگاه» (roadmap D3.6, started in S19; S35 adds contact details, logo, maintenance mode …). «جستجو» pins
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
            'whatsapp_number' => $settings->whatsAppNumber(),
            'telegram_username' => $settings->telegramUsername(),
            'chat_message_product' => $settings->get(StoreSettings::CHAT_MESSAGE_PRODUCT),
            'chat_message_general' => $settings->generalChatMessage(),
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
                        TextInput::make('shipping_flat_rate')
                            ->label('هزینه ارسال')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required()
                            ->helperText('یک هزینه برای همه سفارش‌ها تا راه‌اندازی تیپاکس و پیک؛ بالای حد ارسال رایگان یا با کوپن ارسال رایگان، رایگان است.'),
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
            StoreSettings::WHATSAPP_NUMBER => IranMobile::normalize((string) ($data['whatsapp_number'] ?? '')) ?? '',
            StoreSettings::TELEGRAM_USERNAME => (string) ($data['telegram_username'] ?? ''),
            StoreSettings::CHAT_MESSAGE_PRODUCT => trim((string) $data['chat_message_product']),
            StoreSettings::CHAT_MESSAGE_GENERAL => trim((string) $data['chat_message_general']),
            StoreSettings::SEARCH_CATEGORY_IDS => array_values(array_map(intval(...), (array) ($data['search_category_ids'] ?? []))),
            StoreSettings::SEARCH_BRAND_IDS => array_values(array_map(intval(...), (array) ($data['search_brand_ids'] ?? []))),
        ]);

        Notification::make()->success()->title('تنظیمات ذخیره شد.')->send();
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
