<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Drivers;

use App\Modules\Notifications\Contracts\SmsSender;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Data\SmsResultData;
use App\Modules\Notifications\Enums\MelipayamakError;
use App\Modules\System\Support\IntegrationHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Melli Payamak pattern SMS over REST (roadmap D11.6, method SendByBaseNumber):
 * POST {base_url}{send_pattern_path} (config/sms.php, .env) with username, the API key as «password», the variables joined by «;»
 * as «text», the recipient and the pattern's bodyId. A return value longer than 15 digits is the message id;
 * anything else is a MelipayamakError. Connection failures and 5xx answers are tried again (config «retries»). The call is written to the integration log with the API key (and, for
 * secret templates such as the sign-in code, the text) hidden.
 */
final readonly class MelipayamakSmsSender implements SmsSender
{
    public function __construct(
        private string $username,
        private string $apiKey,
        private string $baseUrl,
        private string $sendPatternPath,
        private int $connectTimeoutSeconds,
        private int $timeoutSeconds,
        private int $retries,
        private int $retrySleepMs,
    ) {}

    public function send(SmsMessageData $message, string $patternId): SmsResultData
    {
        $redact = $message->template->isSecret() ? ['text'] : [];

        try {
            $response = IntegrationHttp::for('melipayamak', 'send_pattern', redact: $redact)
                ->asForm()
                ->acceptJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->retry(
                    max(1, $this->retries),
                    $this->retrySleepMs,
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                )
                ->post(rtrim($this->baseUrl, '/').'/'.ltrim($this->sendPatternPath, '/'), [
                    'username' => $this->username,
                    'password' => $this->apiKey,
                    'text' => implode(';', $message->args),
                    'to' => $message->mobile,
                    'bodyId' => $patternId,
                ])
                ->throw();
        } catch (ConnectionException) {
            return new SmsResultData(success: false, providerMessageId: null, code: 'timeout', error: 'سامانه پیامک پاسخ نداد', retryable: true);
        } catch (RequestException $exception) {
            return new SmsResultData(success: false, providerMessageId: null, code: 'http_'.$exception->response->status(), error: 'خطای سامانه پیامک', retryable: $exception->response->serverError());
        }

        $value = trim((string) ($response->json('Value') ?? $response->json('value') ?? $response->body()), " \"\n\r\t");

        if (preg_match('/^\d{16,}$/', $value) === 1) {
            return new SmsResultData(success: true, providerMessageId: $value, code: 'ok');
        }

        $error = MelipayamakError::tryFrom($value);

        return new SmsResultData(
            success: false,
            providerMessageId: null,
            code: $value === '' ? 'empty' : $value,
            error: $error?->label() ?? 'پاسخ ناشناخته از سامانه پیامک',
            retryable: $error?->isRetryable() ?? false,
        );
    }
}
