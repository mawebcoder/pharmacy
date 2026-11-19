<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Drivers;

use App\Modules\Notifications\Contracts\SmsSender;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Data\SmsResultData;
use App\Modules\Notifications\Enums\KavenegarError;
use Closure;
use Kavenegar\Exceptions\ApiException;
use Kavenegar\KavenegarApi;
use Kavenegar\Models\SendResult;
use Throwable;

/**
 * Kavenegar through its package (kavenegar/php; owner 2026-11-18): a template SMS with verify/lookup, the message's
 * variables in the template's %token%, %token2% and %token3%. A refusal (ApiException) is a failed send with a Persian
 * reason; no connection (HttpException) is retryable, so SendSmsJob tries again. The package turns SSL checks off by
 * default; here they are on.
 */
final readonly class KavenegarSmsSender implements SmsSender
{
    /**
     * @param  Closure(): KavenegarApi  $api  builds the package's client (tests swap it)
     */
    public function __construct(private Closure $api) {}

    public static function client(string $apiKey, string $baseUrl): KavenegarApi
    {
        return new KavenegarApi($apiKey, false, $baseUrl, true);
    }

    public function send(SmsMessageData $message, string $patternId): SmsResultData
    {
        [$token, $token2, $token3] = array_pad($message->args, 3, null);

        try {
            $result = ($this->api)()->verifyLookup($message->mobile, (string) $token, $token2, $token3, $patternId);
        } catch (ApiException $refused) {
            $error = KavenegarError::tryFrom($refused->getCode());

            return new SmsResultData(
                success: false,
                providerMessageId: null,
                code: (string) $refused->getCode(),
                error: $error?->label() ?? 'خطای سامانه پیامک کاوه‌نگار',
                retryable: $error?->isRetryable() ?? false,
            );
        } catch (Throwable) {
            // HttpException (no connection) and anything else the package throws: try again later.
            return new SmsResultData(success: false, providerMessageId: null, code: 'timeout', error: 'سامانه پیامک پاسخ نداد', retryable: true);
        }

        $sent = is_array($result) ? ($result[0] ?? null) : $result;

        if (! $sent instanceof SendResult || $sent->messageid === null) {
            return new SmsResultData(success: false, providerMessageId: null, code: 'empty', error: 'پاسخ ناشناخته از سامانه پیامک');
        }

        return new SmsResultData(success: true, providerMessageId: (string) $sent->messageid, code: 'ok');
    }
}
