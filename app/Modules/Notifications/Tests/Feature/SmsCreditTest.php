<?php

declare(strict_types=1);

use App\Modules\Identity\Models\AdminUser;
use App\Modules\Notifications\Actions\SendTemplatedSms;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\CheckSmsCredit;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Http;
use Kavenegar\KavenegarApi;
use Kavenegar\Models\AccountInfoResult;

/*
| Low SMS credit (roadmap D11.6, S37; owner 2026-11-27): super admins are alerted under the admin panel's bell when a
| send is refused for lack of credit, or when the daily check finds the credit below «sms.low_credit_below».
*/

const MELIPAYAMAK_CREDIT_URL = 'https://rest.payamak-panel.com/api/SendSMS/GetCredit';

beforeEach(function (): void {
    config([
        'sms.pretend' => false,
        'sms.provider' => 'melipayamak',
        'sms.low_credit_below' => 1_000_000,
        'sms.providers.melipayamak.username' => 'pharmacy-user',
        'sms.providers.melipayamak.api_key' => 'test-api-key-123',
        'sms.providers.melipayamak.retry_sleep_ms' => 0,
        'sms.providers.melipayamak.patterns.order_paid' => '223344',
    ]);

    $this->superAdmin = AdminUser::factory()->superAdmin()->create();
});

/**
 * Alerts kept for admins under the panel's bell.
 *
 * @return list<string>
 */
function adminAlertTitles(): array
{
    return DatabaseNotification::query()
        ->where('notifiable_type', (new AdminUser)->getMorphClass())
        ->get()
        ->map(fn (DatabaseNotification $notification): string => (string) $notification->data['title'])
        ->all();
}

it('alerts active super admins once an hour when sends are refused for lack of credit', function (): void {
    AdminUser::factory()->superAdmin()->inactive()->create();
    Http::fake(['*' => Http::response(['Value' => '2', 'RetStatus' => 0])]);
    $message = new SmsMessageData('09123456789', SmsTemplate::OrderPaid, ['سارا', 'PH-140508-000123']);

    $first = app(SendTemplatedSms::class)($message);
    $second = app(SendTemplatedSms::class)($message);

    expect($first->outOfCredit)->toBeTrue()
        ->and($second->success)->toBeFalse()
        ->and(adminAlertTitles())->toBe(['اعتبار پنل پیامک تمام شده است'])
        ->and(DatabaseNotification::query()->sole()->notifiable_id)->toBe($this->superAdmin->getKey());
});

it('does not alert for other refusals', function (): void {
    Http::fake(['*' => Http::response(['Value' => '-5', 'RetStatus' => 0])]);

    $result = app(SendTemplatedSms::class)(new SmsMessageData('09123456789', SmsTemplate::OrderPaid, ['سارا', 'PH-140508-000123']));

    expect($result->outOfCredit)->toBeFalse()
        ->and(adminAlertTitles())->toBe([]);
});

it('alerts when the daily check finds the credit below the threshold, with what is left', function (): void {
    Http::fake([MELIPAYAMAK_CREDIT_URL => Http::response(['Value' => '450000.75', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);

    dispatch_sync(new CheckSmsCredit);

    Http::assertSent(fn ($request): bool => $request->url() === MELIPAYAMAK_CREDIT_URL
        && $request['username'] === 'pharmacy-user'
        && $request['password'] === 'test-api-key-123');
    expect(adminAlertTitles())->toBe(['اعتبار پنل پیامک کم است'])
        ->and(DatabaseNotification::query()->sole()->data['body'])->toContain('۴۵٬۰۰۰ تومان');
});

it('does not alert when the credit is enough, the provider does not answer, or SMS are pretended', function (string $case): void {
    match ($case) {
        'enough' => Http::fake([MELIPAYAMAK_CREDIT_URL => Http::response(['Value' => '5000000', 'RetStatus' => 1])]),
        'no answer' => Http::fake([MELIPAYAMAK_CREDIT_URL => Http::response('', 500)]),
        'pretend' => tap(Http::fake(), fn () => config(['sms.pretend' => true])),
    };

    dispatch_sync(new CheckSmsCredit);

    expect(adminAlertTitles())->toBe([]);

    if ($case === 'pretend') {
        Http::assertNothingSent();
    }
})->with(['enough', 'no answer', 'pretend']);

it('reads Kavenegar\'s remaining credit from its account info', function (): void {
    config(['sms.provider' => 'kavenegar', 'sms.providers.kavenegar.api_key' => 'test-key']);
    app()->instance(KavenegarApi::class, new class extends KavenegarApi
    {
        public function __construct()
        {
            parent::__construct('test-key', false, 'https://kavenegar.test', true);
        }

        public function accountInfo()
        {
            return new AccountInfoResult((object) ['remaincredit' => 120000, 'expiredate' => 0, 'type' => 'master']);
        }
    });

    dispatch_sync(new CheckSmsCredit);

    expect(adminAlertTitles())->toBe(['اعتبار پنل پیامک کم است'])
        ->and(DatabaseNotification::query()->sole()->data['body'])->toContain('کاوه‌نگار');
});
