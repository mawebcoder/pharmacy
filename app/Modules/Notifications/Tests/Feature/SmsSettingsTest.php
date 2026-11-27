<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Filament\Pages\SmsSettingsPage;
use App\Modules\Notifications\Filament\Widgets\SmsCreditWidget;
use App\Modules\Notifications\Support\SmsManager;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

/*
| «تنظیمات پیامک» (roadmap D11.6, S37; owner 2026-11-27): super admins set the SMS provider, its credentials (API keys
| encrypted, never shown or logged), pattern ids and the low-credit threshold; empty fields fall back to .env. The
| dashboard shows the panel's credit.
*/

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    config([
        'sms.pretend' => false,
        'sms.provider' => 'melipayamak',
        'sms.low_credit_below' => 1_000_000,
        'sms.providers.melipayamak.username' => 'env-user',
        'sms.providers.melipayamak.api_key' => 'env-key',
        'sms.providers.melipayamak.patterns.otp' => '111111',
    ]);
});

function actingAsSuperAdmin(): void
{
    test()->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
}

it('is for super admins only', function (): void {
    test()->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    livewire(SmsSettingsPage::class)->assertForbidden();

    actingAsSuperAdmin();
    livewire(SmsSettingsPage::class)->assertOk()->assertSee('تنظیمات پیامک');
});

it('saves the provider, credentials, patterns and threshold, and they win over .env', function (): void {
    actingAsSuperAdmin();

    livewire(SmsSettingsPage::class)
        ->fillForm([
            'provider' => 'kavenegar',
            'low_credit_below' => '250,000',
            'melipayamak_username' => 'panel-user',
            'melipayamak_api_key' => 'admin-secret-key',
            'patterns.melipayamak.otp' => '998877',
            'patterns.kavenegar.order_paid' => 'hutana-paid',
            'return_notify_mobiles' => ['۰۹۱۲۱۱۱۰۰۰۰'],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.melipayamak_api_key', null);

    $sms = app(SmsManager::class);

    expect($sms->provider())->toBe(SmsProvider::Kavenegar)
        ->and($sms->lowCreditBelow())->toBe(2_500_000)
        ->and($sms->credential(SmsProvider::Melipayamak, 'username'))->toBe('panel-user')
        ->and($sms->credential(SmsProvider::Melipayamak, 'api_key'))->toBe('admin-secret-key')
        ->and($sms->patternId(SmsTemplate::Otp, SmsProvider::Melipayamak))->toBe('998877')
        ->and($sms->patternId(SmsTemplate::OrderPaid))->toBe('hutana-paid')
        ->and($sms->patternId(SmsTemplate::OrderCancelled, SmsProvider::Melipayamak))->toBeNull()
        ->and(app(StoreSettings::class)->returnNotifyMobiles())->toBe(['09121110000']);
});

it('falls back to .env for what the admin left empty', function (): void {
    $sms = app(SmsManager::class);

    expect($sms->provider())->toBe(SmsProvider::Melipayamak)
        ->and($sms->credential(SmsProvider::Melipayamak, 'api_key'))->toBe('env-key')
        ->and($sms->patternId(SmsTemplate::Otp))->toBe('111111')
        ->and($sms->lowCreditBelow())->toBe(1_000_000);
});

it('stores API keys encrypted, keeps them when the field is left empty, and never logs them', function (): void {
    actingAsSuperAdmin();

    livewire(SmsSettingsPage::class)->fillForm(['melipayamak_api_key' => 'admin-secret-key'])->call('save')->assertHasNoFormErrors();
    livewire(SmsSettingsPage::class)->fillForm(['melipayamak_username' => 'changed-user'])->call('save')->assertHasNoFormErrors();

    $stored = Setting::query()->where(Setting::COLUMN_KEY, StoreSettings::SMS_MELIPAYAMAK_API_KEY)->sole()->getValue();

    expect($stored)->not->toContain('admin-secret-key')
        ->and(app(SmsManager::class)->credential(SmsProvider::Melipayamak, 'api_key'))->toBe('admin-secret-key')
        ->and(DB::table('activity_log')->where('properties', 'like', '%admin-secret-key%')->exists())->toBeFalse()
        ->and(DB::table('activity_log')->where('properties', 'like', '%'.substr((string) $stored, 0, 20).'%')->exists())->toBeFalse();
});

it('sends a test sign-in code with the saved settings', function (): void {
    actingAsSuperAdmin();
    Http::fake(['*' => Http::response(['Value' => '1234567890123456789', 'RetStatus' => 1])]);

    livewire(SmsSettingsPage::class)
        ->callAction('sendTest', ['mobile' => '09123456789'])
        ->assertNotified('پیامک ارسال شد.');

    Http::assertSent(fn ($request): bool => $request['to'] === '09123456789' && $request['bodyId'] === '111111' && $request['password'] === 'env-key');
});

it('shows the SMS panel\'s credit on the dashboard, red below the threshold', function (): void {
    actingAsSuperAdmin();
    Http::fake(['*/GetCredit' => Http::response(['Value' => '450000', 'RetStatus' => 1])]);

    livewire(SmsCreditWidget::class)->assertSee('۴۵٬۰۰۰ تومان')->assertSee('پنل را شارژ کنید');
});

it('says when SMS are pretended or the provider does not answer', function (): void {
    actingAsSuperAdmin();

    config(['sms.pretend' => true]);
    livewire(SmsCreditWidget::class)->assertSee('حالت آزمایشی');

    cache()->flush();
    config(['sms.pretend' => false]);
    Http::fake(['*/GetCredit' => Http::response('', 500)]);
    livewire(SmsCreditWidget::class)->assertSee('نامشخص');
});

it('hides the credit from staff other than super admins', function (): void {
    test()->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');

    expect(SmsCreditWidget::canView())->toBeFalse();
});
