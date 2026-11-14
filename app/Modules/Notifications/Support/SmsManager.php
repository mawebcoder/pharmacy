<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Contracts\SmsSender;
use App\Modules\Notifications\Drivers\LogSmsSender;
use App\Modules\Notifications\Drivers\MelipayamakSmsSender;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;

/**
 * Picks the SMS sender for a provider (roadmap D11.6, strategy pattern). The provider comes from SMS_PROVIDER unless
 * a caller names one; with SMS_PRETEND on every provider is served by the log sender, so nothing leaves the app.
 */
final class SmsManager
{
    /** @var array<string, SmsSender> */
    private array $senders = [];

    public function provider(?SmsProvider $provider = null): SmsProvider
    {
        return $provider ?? SmsProvider::configured();
    }

    public function isPretending(): bool
    {
        return (bool) config('sms.pretend');
    }

    public function sender(?SmsProvider $provider = null): SmsSender
    {
        $provider = $this->provider($provider);

        if ($this->isPretending()) {
            return $this->senders['pretend'] ??= new LogSmsSender;
        }

        return $this->senders[$provider->value] ??= match ($provider) {
            SmsProvider::Melipayamak => new MelipayamakSmsSender(
                username: (string) $this->setting($provider, 'username'),
                apiKey: (string) $this->setting($provider, 'api_key'),
                baseUrl: (string) $this->setting($provider, 'base_url'),
                sendPatternPath: (string) $this->setting($provider, 'send_pattern_path'),
                connectTimeoutSeconds: (int) $this->setting($provider, 'connect_timeout_seconds'),
                timeoutSeconds: (int) $this->setting($provider, 'timeout_seconds'),
                retries: (int) $this->setting($provider, 'retries'),
                retrySleepMs: (int) $this->setting($provider, 'retry_sleep_ms'),
            ),
        };
    }

    /**
     * The provider's pattern id for a template, or null when it is not set up yet.
     */
    public function patternId(SmsTemplate $template, ?SmsProvider $provider = null): ?string
    {
        $patternId = (string) $this->setting($this->provider($provider), 'patterns.'.$template->value);

        return $patternId === '' ? null : $patternId;
    }

    private function setting(SmsProvider $provider, string $key): mixed
    {
        return config('sms.providers.'.$provider->value.'.'.$key);
    }
}
