<?php

declare(strict_types=1);

use App\Modules\Notifications\Actions\SendTemplatedSms;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Drivers\LogSmsSender;
use App\Modules\Notifications\Drivers\MelipayamakSmsSender;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Exceptions\SmsNotSent;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Notifications\Models\SmsLog;
use App\Modules\Notifications\Support\SmsManager;
use App\Modules\System\Models\IntegrationLog;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const MELIPAYAMAK_SEND_URL = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';

/**
 * Real-provider settings with fake credentials; HTTP is always faked in these tests.
 */
function useMelipayamak(): void
{
    config([
        'sms.pretend' => false,
        'sms.provider' => 'melipayamak',
        'sms.providers.melipayamak.username' => 'pharmacy-user',
        'sms.providers.melipayamak.api_key' => 'test-api-key-123',
        'sms.providers.melipayamak.retry_sleep_ms' => 0,
        'sms.providers.melipayamak.patterns.otp' => '123456',
    ]);
}

function otpMessage(string $code = '48213'): SmsMessageData
{
    return new SmsMessageData('09123456789', SmsTemplate::Otp, [$code]);
}

it('picks the sender for the provider, and the log sender while pretending', function (): void {
    config(['sms.pretend' => true]);
    expect(app(SmsManager::class)->sender(SmsProvider::Melipayamak))->toBeInstanceOf(LogSmsSender::class);

    useMelipayamak();
    expect((new SmsManager)->sender())->toBeInstanceOf(MelipayamakSmsSender::class)
        ->and((new SmsManager)->provider())->toBe(SmsProvider::Melipayamak);
});

it('pretends to send without calling the provider and logs a masked row without the code', function (): void {
    config(['sms.pretend' => true]);
    Http::fake();

    $result = app(SendTemplatedSms::class)(otpMessage());

    $log = SmsLog::query()->sole();
    Http::assertNothingSent();
    expect($result->success)->toBeTrue()
        ->and($log->getProvider())->toBe(SmsProvider::Melipayamak)
        ->and($log->isPretend())->toBeTrue()
        ->and($log->getRecipient())->toBe('0912***6789')
        ->and($log->getVariables())->toBeNull()
        ->and(json_encode($log->toArray()))->not->toContain('48213');
});

it('sends a Melli Payamak pattern SMS and logs the message id', function (): void {
    useMelipayamak();
    Http::fake([MELIPAYAMAK_SEND_URL => Http::response(['Value' => '1234567890123456789', 'RetStatus' => 1])]);

    $result = app(SendTemplatedSms::class)(otpMessage());

    Http::assertSent(fn (Request $request): bool => $request['username'] === 'pharmacy-user'
        && $request['password'] === 'test-api-key-123'
        && $request['to'] === '09123456789'
        && $request['text'] === '48213'
        && $request['bodyId'] === '123456');
    $log = SmsLog::query()->sole();
    expect($result->success)->toBeTrue()
        ->and($log->isPretend())->toBeFalse()
        ->and($log->getProviderMessageId())->toBe('1234567890123456789')
        ->and($log->getPatternId())->toBe('123456');
});

it('hides the API key and the sign-in code in the integration log', function (): void {
    useMelipayamak();
    Http::fake([MELIPAYAMAK_SEND_URL => Http::response(['Value' => '1234567890123456789'])]);

    expect(app(SendTemplatedSms::class)(otpMessage())->success)->toBeTrue();

    $stored = (string) json_encode(IntegrationLog::query()->sole()->toArray());
    expect($stored)->not->toContain('test-api-key-123')
        ->and($stored)->not->toContain('48213');
});

it('turns a Melli Payamak error code into a Persian error', function (): void {
    useMelipayamak();
    Http::fake([MELIPAYAMAK_SEND_URL => Http::response(['Value' => '-110'])]);

    $result = app(SendTemplatedSms::class)(otpMessage());

    expect($result->success)->toBeFalse()
        ->and($result->code)->toBe('-110')
        ->and($result->retryable)->toBeFalse()
        ->and(SmsLog::query()->sole()->getError())->not->toBeNull();
});

it('fails without calling the provider when the template has no pattern id', function (): void {
    useMelipayamak();
    config(['sms.providers.melipayamak.patterns.otp' => null]);
    Http::fake();

    $result = app(SendTemplatedSms::class)(otpMessage());

    Http::assertNothingSent();
    expect($result->code)->toBe('no_pattern')
        ->and(SmsLog::query()->sole()->isSuccess())->toBeFalse();
});

it('retries the HTTP call on server errors, three times in all', function (): void {
    useMelipayamak();
    Http::fake([MELIPAYAMAK_SEND_URL => Http::sequence()
        ->push('error', 502)
        ->push('error', 503)
        ->push(['Value' => '1234567890123456789'])]);

    $result = app(SendTemplatedSms::class)(otpMessage());

    Http::assertSentCount(3);
    expect($result->success)->toBeTrue();
});

it('gives up after three failed connections with a retryable timeout', function (): void {
    useMelipayamak();
    $calls = 0;
    Http::fake(function () use (&$calls): never {
        $calls++;

        throw new ConnectionException('timed out');
    });

    $result = app(SendTemplatedSms::class)(otpMessage());

    expect($calls)->toBe(3);
    expect($result->success)->toBeFalse()
        ->and($result->code)->toBe('timeout')
        ->and($result->retryable)->toBeTrue();
});

it('lets the queue retry the job only for a retryable failure', function (int $status, string $value, bool $retried): void {
    useMelipayamak();
    Http::fake([MELIPAYAMAK_SEND_URL => Http::response(['Value' => $value], $status)]);

    $handle = fn () => (new SendSmsJob(otpMessage()))->handle(app(SendTemplatedSms::class));

    $retried ? expect($handle)->toThrow(SmsNotSent::class) : expect($handle)->not->toThrow(SmsNotSent::class);
})->with([
    'server error' => [500, '', true],
    'provider busy (-7)' => [200, '-7', true],
    'wrong credentials (-110)' => [200, '-110', false],
]);

it('queues the job on the sms queue with backoff and a 60-second expiry', function (): void {
    $job = new SendSmsJob(otpMessage());

    expect($job->queue)->toBe('sms')
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30])
        ->and($job->retryUntil()->getTimestamp())->toBeBetween(now()->addSeconds(59)->getTimestamp(), now()->addSeconds(60)->getTimestamp())
        ->and($job)->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('writes nothing about the code to the application log outside pretend mode', function (): void {
    useMelipayamak();
    Http::fake([MELIPAYAMAK_SEND_URL => Http::response(['Value' => '1234567890123456789'])]);
    Log::spy();

    expect(app(SendTemplatedSms::class)(otpMessage())->success)->toBeTrue();

    Log::shouldNotHaveReceived('info');
});
