<?php

declare(strict_types=1);

use App\Modules\Notifications\Actions\SendTemplatedSms;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Drivers\KavenegarSmsSender;
use App\Modules\Notifications\Drivers\LogSmsSender;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Models\SmsLog;
use App\Modules\Notifications\Support\SmsManager;
use Kavenegar\Exceptions\ApiException;
use Kavenegar\Exceptions\HttpException;
use Kavenegar\KavenegarApi;
use Kavenegar\Models\SendResult;

/*
| Kavenegar through its package (owner, 2026-11-18). The package calls cURL itself, so the tests swap its client for a
| fake that records the lookup and answers like Kavenegar's API.
*/

/**
 * A KavenegarApi that records verifyLookup calls and answers with $answer (a send result, or an exception to throw).
 */
function fakeKavenegar(SendResult|Throwable $answer): object
{
    $fake = new class($answer) extends KavenegarApi
    {
        /** @var list<array<string, mixed>> */
        public array $lookups = [];

        public function __construct(private readonly SendResult|Throwable $answer)
        {
            parent::__construct('test-key', false, 'https://kavenegar.test', true);
        }

        public function verifyLookup($receptor, $token, $token2 = null, $token3 = null, $template = null, $type = null, $token10 = null, $token20 = null, $tag = null)
        {
            $this->lookups[] = compact('receptor', 'token', 'token2', 'token3', 'template');

            if ($this->answer instanceof Throwable) {
                throw $this->answer;
            }

            return [$this->answer];
        }
    };

    app()->instance(KavenegarApi::class, $fake);

    return $fake;
}

beforeEach(function (): void {
    config([
        'sms.pretend' => false,
        'sms.provider' => 'kavenegar',
        'sms.providers.kavenegar.api_key' => 'test-key',
        'sms.providers.kavenegar.patterns.otp' => 'hutana-otp',
    ]);
});

function kavenegarOtp(): SmsMessageData
{
    return new SmsMessageData('09123456789', SmsTemplate::Otp, ['48213']);
}

it('sends the template with the code as its token and keeps Kavenegar\'s message id, never the code', function (): void {
    $kavenegar = fakeKavenegar(new SendResult((object) ['messageid' => 8792343, 'status' => 1, 'receptor' => '09123456789']));

    $result = app(SendTemplatedSms::class)(kavenegarOtp());

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('8792343')
        ->and($kavenegar->lookups)->toBe([['receptor' => '09123456789', 'token' => '48213', 'token2' => null, 'token3' => null, 'template' => 'hutana-otp']]);
    $log = SmsLog::query()->sole();
    expect($log->getProvider())->toBe(SmsProvider::Kavenegar)
        ->and($log->getProviderMessageId())->toBe('8792343')
        ->and($log->getVariables())->toBeNull();
});

it('reports Kavenegar\'s refusal in Persian, retrying only when it may work later', function (int $status, string $error, bool $retryable): void {
    fakeKavenegar(new ApiException('refused', $status));

    $result = app(SendTemplatedSms::class)(kavenegarOtp());

    expect($result->success)->toBeFalse()
        ->and($result->code)->toBe((string) $status)
        ->and($result->error)->toBe($error)
        ->and($result->retryable)->toBe($retryable);
})->with([
    'bad key' => [403, 'کلید API کاوه‌نگار معتبر نیست', false],
    'no credit' => [418, 'اعتبار حساب کاوه‌نگار کافی نیست', false],
    'account not verified' => [430, 'حساب کاوه‌نگار احراز هویت نشده است', false],
    'unknown template' => [424, 'الگوی پیامک در کاوه‌نگار پیدا نشد یا تأیید نشده است', false],
    'busy' => [409, 'سرور کاوه‌نگار مشغول است', true],
]);

it('retries later when Kavenegar cannot be reached', function (): void {
    fakeKavenegar(new HttpException('Could not resolve host', 6));

    $result = app(SendTemplatedSms::class)(kavenegarOtp());

    expect($result->success)->toBeFalse()
        ->and($result->retryable)->toBeTrue();
});

it('refuses to send without a template name, and sends nothing while pretending', function (): void {
    $kavenegar = fakeKavenegar(new SendResult((object) ['messageid' => 1]));
    config(['sms.providers.kavenegar.patterns.otp' => null]);

    expect(app(SendTemplatedSms::class)(kavenegarOtp())->code)->toBe('no_pattern');

    config(['sms.pretend' => true]);
    expect(app(SmsManager::class)->sender(SmsProvider::Kavenegar))->toBeInstanceOf(LogSmsSender::class)
        ->and($kavenegar->lookups)->toBe([]);
});

it('builds the package client with SSL checks on', function (): void {
    expect(KavenegarSmsSender::client('key', 'https://api.kavenegar.com')->getSslVerify())->toBeTrue();
});
