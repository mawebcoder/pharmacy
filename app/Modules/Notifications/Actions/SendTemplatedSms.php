<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Data\SmsResultData;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Models\SmsLog;
use App\Modules\Notifications\Support\SmsManager;

/**
 * Sends one template SMS now (roadmap D11.6) through the configured provider, or the one passed in, and writes
 * sms_logs (masked mobile, no secret variables). A template without a pattern id fails without calling the
 * provider, except in pretend mode.
 */
final readonly class SendTemplatedSms
{
    public function __construct(private SmsManager $sms) {}

    #[\NoDiscard]
    public function __invoke(SmsMessageData $message, ?SmsProvider $provider = null): SmsResultData
    {
        $provider = $this->sms->provider($provider);
        $patternId = $this->sms->patternId($message->template, $provider);
        $started = hrtime(true);

        $result = $patternId === null && ! $this->sms->isPretending()
            ? new SmsResultData(success: false, providerMessageId: null, code: 'no_pattern', error: 'الگوی پیامک «'.$message->template->label().'» برای '.$provider->label().' تنظیم نشده است')
            : $this->sms->sender($provider)->send($message, $patternId ?? '');

        (new SmsLog)
            ->setProvider($provider)
            ->setTemplate($message->template)
            ->setRecipient(SmsLog::mask($message->mobile))
            ->setVariables($message->template->isSecret() ? null : $message->args)
            ->setPatternId($patternId)
            ->setIsPretend($this->sms->isPretending())
            ->setSuccess($result->success)
            ->setProviderMessageId($result->providerMessageId)
            ->setCode($result->code)
            ->setError($result->error)
            ->setDurationMs((int) round((hrtime(true) - $started) / 1_000_000))
            ->save();

        return $result;
    }
}
