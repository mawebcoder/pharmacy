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
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

/*
| «تنظیمات پیامک» (roadmap D11.6, S37): super admins set the low-credit threshold and the return-request mobiles; the
| provider, its credentials and pattern ids come from .env only (owner, 2026-11-28). The dashboard shows the credit.
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

it('saves the threshold and the return mobiles, and offers no field for the provider, keys or patterns', function (): void {
    actingAsSuperAdmin();

    livewire(SmsSettingsPage::class)
        ->assertFormFieldDoesNotExist('provider')
        ->assertFormFieldDoesNotExist('melipayamak_api_key')
        ->assertFormFieldDoesNotExist('patterns.melipayamak.otp')
        ->fillForm(['low_credit_below' => '250,000', 'return_notify_mobiles' => ['۰۹۱۲۱۱۱۰۰۰۰']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(SmsManager::class)->lowCreditBelow())->toBe(2_500_000)
        ->and(app(StoreSettings::class)->returnNotifyMobiles())->toBe(['09121110000']);
});

it('takes the provider, credentials and pattern ids from .env only, even with values saved before', function (): void {
    foreach (['sms.provider' => 'kavenegar', 'sms.melipayamak.username' => 'old-user', 'sms.patterns' => ['melipayamak' => ['otp' => '998877']]] as $key => $value) {
        (new Setting)->setSettingKey($key)->setValue($value)->save();
    }

    $sms = app(SmsManager::class);

    expect($sms->provider())->toBe(SmsProvider::Melipayamak)
        ->and($sms->credential(SmsProvider::Melipayamak, 'username'))->toBe('env-user')
        ->and($sms->credential(SmsProvider::Melipayamak, 'api_key'))->toBe('env-key')
        ->and($sms->patternId(SmsTemplate::Otp))->toBe('111111')
        ->and($sms->lowCreditBelow())->toBe(1_000_000);
});

it('sends a test sign-in code with the .env settings', function (): void {
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
