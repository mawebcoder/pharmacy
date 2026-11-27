<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Filament\Pages;

use App\Modules\Notifications\Actions\SendTemplatedSms;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Notifications\Queries\SmsCredit;
use App\Modules\Notifications\Support\SmsManager;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shared\Rules\IranianMobile;
use App\Modules\Shared\Support\IranMobile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
 * «تنظیمات پیامک» (roadmap D11.6, S37; owner 2026-11-27), for super admins: the SMS provider, its username and API
 * key (saved encrypted and never shown again; an empty field keeps the saved key), each template's pattern id per
 * provider, the low-credit threshold and the staff mobiles texted about return requests. Empty fields fall back to
 * .env. «ارسال پیامک آزمایشی» sends a test sign-in code. Pretend mode (SMS_PRETEND) stays in .env only.
 *
 * @property-read Schema $form
 */
final class SmsSettingsPage extends Page
{
    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'تنظیمات پیامک';

    protected static ?string $title = 'تنظیمات پیامک';

    protected static ?string $slug = 'sms-settings';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can(NotificationsServiceProvider::MANAGE_SMS) ?? false;
    }

    public function mount(StoreSettings $settings, SmsManager $sms): void
    {
        $patterns = [];

        foreach (SmsProvider::cases() as $provider) {
            foreach (SmsTemplate::cases() as $template) {
                $patterns[$provider->value][$template->value] = $settings->smsPatternId($provider->value, $template->value);
            }
        }

        $this->form->fill([
            'provider' => $sms->provider()->value,
            'low_credit_below' => $sms->lowCreditBelow(),
            'melipayamak_username' => $settings->smsSetting(StoreSettings::SMS_MELIPAYAMAK_USERNAME),
            'melipayamak_api_key' => null,
            'kavenegar_api_key' => null,
            'patterns' => $patterns,
            'return_notify_mobiles' => $settings->returnNotifyMobiles(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(StoreSettings::class);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('سرویس پیامک')
                    ->description(config('sms.pretend')
                        ? 'حالت آزمایشی (SMS_PRETEND در .env) روشن است: پیامک‌ها فقط در لاگ نوشته می‌شوند و ارسال نمی‌شوند.'
                        : 'پیامک‌های ورود و سفارش با این سرویس ارسال می‌شوند. فیلدهای خالی از مقدار .env استفاده می‌کنند.')
                    ->columns(2)
                    ->schema([
                        Select::make('provider')
                            ->label('سرویس‌دهنده')
                            ->options(SmsProvider::options())
                            ->required()
                            ->native(false),
                        TextInput::make('low_credit_below')
                            ->label('هشدار کمبود اعتبار زیر')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required()
                            ->helperText('اگر اعتبار پنل از این مبلغ کمتر شود (بررسی روزانه ساعت ۹) یا پیامکی به علت نبود اعتبار ارسال نشود، به مدیران ارشد هشدار داده می‌شود.'),
                    ]),
                Section::make(SmsProvider::Melipayamak->label())
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('melipayamak_username')
                            ->label('نام کاربری پنل')
                            ->maxLength(100)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        self::apiKeyInput('melipayamak_api_key', $settings->hasSmsSecret(StoreSettings::SMS_MELIPAYAMAK_API_KEY)),
                        ...self::patternInputs(SmsProvider::Melipayamak, 'شناسه الگو (bodyId)'),
                    ]),
                Section::make(SmsProvider::Kavenegar->label())
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        self::apiKeyInput('kavenegar_api_key', $settings->hasSmsSecret(StoreSettings::SMS_KAVENEGAR_API_KEY)),
                        ...self::patternInputs(SmsProvider::Kavenegar, 'نام قالب'),
                    ]),
                Section::make('اعلان مرجوعی')
                    ->description('وقتی مشتری از حساب کاربری‌اش درخواست مرجوعی ثبت می‌کند، به این شماره‌ها پیامک «درخواست مرجوعی» می‌رود.')
                    ->schema([
                        TagsInput::make('return_notify_mobiles')
                            ->label('موبایل‌های دریافت پیامک')
                            ->placeholder('09121234567')
                            ->nestedRecursiveRules([new IranianMobile])
                            ->helperText('هر شماره را بنویسید و Enter بزنید.'),
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

    public function save(SaveStoreSettings $saveSettings, SmsCredit $credit): void
    {
        $data = $this->form->getState();

        $saveSettings([
            StoreSettings::SMS_PROVIDER => (string) $data['provider'],
            StoreSettings::SMS_LOW_CREDIT_BELOW => (int) $data['low_credit_below'],
            StoreSettings::SMS_MELIPAYAMAK_USERNAME => trim((string) ($data['melipayamak_username'] ?? '')),
            StoreSettings::SMS_MELIPAYAMAK_API_KEY => (string) ($data['melipayamak_api_key'] ?? ''),
            StoreSettings::SMS_KAVENEGAR_API_KEY => (string) ($data['kavenegar_api_key'] ?? ''),
            StoreSettings::SMS_PATTERNS => array_map(
                fn (array $patterns): array => array_filter(array_map(fn (mixed $id): string => trim((string) $id), $patterns), fn (string $id): bool => $id !== ''),
                array_intersect_key((array) ($data['patterns'] ?? []), array_flip(array_column(SmsProvider::cases(), 'value'))),
            ),
            StoreSettings::RETURN_NOTIFY_MOBILES => array_values(array_filter(array_map(
                fn (mixed $mobile): ?string => IranMobile::normalize((string) $mobile),
                (array) ($data['return_notify_mobiles'] ?? []),
            ))),
        ]);

        $credit->forget();
        // The keys are never sent back to the browser.
        $this->data = [...(array) $this->data, 'melipayamak_api_key' => null, 'kavenegar_api_key' => null];

        Notification::make()->success()->title('تنظیمات پیامک ذخیره شد.')->send();
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('ارسال پیامک آزمایشی')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modalDescription('یک کد ورود آزمایشی (۱۲۳۴۵) با تنظیمات ذخیره‌شده به این شماره فرستاده می‌شود.')
                ->schema([
                    TextInput::make('mobile')
                        ->label('موبایل')
                        ->required()
                        ->rules([new IranianMobile])
                        ->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->action(function (array $data, SendTemplatedSms $send, SmsManager $sms): void {
                    $result = $send(new SmsMessageData((string) IranMobile::normalize((string) $data['mobile']), SmsTemplate::Otp, ['12345']));

                    $result->success
                        ? Notification::make()->success()->title($sms->isPretending() ? 'در حالت آزمایشی ثبت شد (ارسال نشد).' : 'پیامک ارسال شد.')->send()
                        : Notification::make()->danger()->title('پیامک ارسال نشد')->body($result->error.' (کد '.$result->code.')')->send();
                }),
        ];
    }

    private static function apiKeyInput(string $field, bool $isSaved): TextInput
    {
        return TextInput::make($field)
            ->label('کلید API')
            ->password()
            ->autocomplete('new-password')
            ->maxLength(200)
            ->placeholder($isSaved ? 'ذخیره شده؛ برای تغییر، کلید تازه را بنویسید' : 'از .env')
            ->helperText('رمزنگاری‌شده ذخیره می‌شود و دوباره نمایش داده نمی‌شود.')
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    /**
     * One field per template; empty uses the .env value, shown as the placeholder.
     *
     * @return list<TextInput>
     */
    private static function patternInputs(SmsProvider $provider, string $label): array
    {
        return array_map(
            fn (SmsTemplate $template): TextInput => TextInput::make('patterns.'.$provider->value.'.'.$template->value)
                ->label($label.': '.$template->label())
                ->maxLength(100)
                ->placeholder((string) (config('sms.providers.'.$provider->value.'.patterns.'.$template->value) ?: 'تنظیم نشده'))
                ->extraInputAttributes(['dir' => 'ltr']),
            SmsTemplate::cases(),
        );
    }
}
