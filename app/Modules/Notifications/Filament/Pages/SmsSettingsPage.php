<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Filament\Pages;

use App\Modules\Notifications\Actions\SendTemplatedSms;
use App\Modules\Notifications\Data\SmsMessageData;
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
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * «تنظیمات پیامک» (roadmap D11.6, S37), for super admins: the low-credit threshold and the staff mobiles texted about
 * return requests, plus «ارسال پیامک آزمایشی» (a test sign-in code). The provider, its credentials and pattern ids
 * are read from .env only and shown here, never edited (owner, 2026-11-28).
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
        $this->form->fill([
            'low_credit_below' => $sms->lowCreditBelow(),
            'return_notify_mobiles' => $settings->returnNotifyMobiles(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $sms = app(SmsManager::class);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('سرویس پیامک')
                    ->description('سرویس‌دهنده، نام کاربری، کلید API و شناسه الگوها فقط از .env خوانده می‌شوند (SMS_PROVIDER و کلیدهای همان سرویس)'
                        .(config('sms.pretend') ? '. حالت آزمایشی (SMS_PRETEND) روشن است: پیامک‌ها فقط در لاگ نوشته می‌شوند و ارسال نمی‌شوند.' : '.'))
                    ->schema([
                        Text::make('سرویس‌دهنده فعلی: '.$sms->provider()->label()),
                        TextInput::make('low_credit_below')
                            ->label('هشدار کمبود اعتبار زیر')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required()
                            ->helperText('اگر اعتبار پنل از این مبلغ کمتر شود (بررسی روزانه ساعت ۹) یا پیامکی به علت نبود اعتبار ارسال نشود، به مدیران ارشد هشدار داده می‌شود.'),
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
            StoreSettings::SMS_LOW_CREDIT_BELOW => (int) $data['low_credit_below'],
            StoreSettings::RETURN_NOTIFY_MOBILES => array_values(array_filter(array_map(
                fn (mixed $mobile): ?string => IranMobile::normalize((string) $mobile),
                (array) ($data['return_notify_mobiles'] ?? []),
            ))),
        ]);

        $credit->forget();

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
                ->modalDescription('یک کد ورود آزمایشی (۱۲۳۴۵) با تنظیمات .env به این شماره فرستاده می‌شود.')
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
}
