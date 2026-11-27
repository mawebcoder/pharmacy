<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Contracts\SmsSender;
use App\Modules\Notifications\Drivers\KavenegarSmsSender;
use App\Modules\Notifications\Drivers\LogSmsSender;
use App\Modules\Notifications\Drivers\MelipayamakSmsSender;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Settings\Queries\StoreSettings;
use Kavenegar\KavenegarApi;

/**
 * Picks the SMS sender for a provider (roadmap D11.6, strategy pattern). The provider, credentials, pattern ids and
 * low-credit threshold come from «تنظیمات پیامک» (S37, owner 2026-11-27), else from .env (config/sms.php), unless a
 * caller names a provider; with SMS_PRETEND on every provider is served by the log sender, so nothing leaves the app.
 * Senders are kept per provider and credentials, so a long-running worker picks up keys changed in the admin.
 */
final class SmsManager
{
    /** @var array<string, SmsSender> */
    private array $senders = [];

    public function __construct(private readonly StoreSettings $settings = new StoreSettings) {}

    public function provider(?SmsProvider $provider = null): SmsProvider
    {
        return $provider
            ?? SmsProvider::tryFrom((string) $this->settings->smsSetting(StoreSettings::SMS_PROVIDER))
            ?? SmsProvider::configured();
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

        $apiKey = (string) $this->credential($provider, 'api_key');
        $username = (string) $this->credential($provider, 'username');

        return $this->senders[$provider->value.':'.hash('sha256', $username.'|'.$apiKey)] ??= match ($provider) {
            SmsProvider::Melipayamak => new MelipayamakSmsSender(
                username: $username,
                apiKey: $apiKey,
                baseUrl: (string) $this->setting($provider, 'base_url'),
                sendPatternPath: (string) $this->setting($provider, 'send_pattern_path'),
                creditPath: (string) $this->setting($provider, 'credit_path'),
                connectTimeoutSeconds: (int) $this->setting($provider, 'connect_timeout_seconds'),
                timeoutSeconds: (int) $this->setting($provider, 'timeout_seconds'),
                retries: (int) $this->setting($provider, 'retries'),
                retrySleepMs: (int) $this->setting($provider, 'retry_sleep_ms'),
            ),
            SmsProvider::Kavenegar => new KavenegarSmsSender(fn (): KavenegarApi => app(KavenegarApi::class)),
        };
    }

    /**
     * A provider's username or API key: the one saved in the admin, else .env.
     */
    public function credential(SmsProvider $provider, string $key): ?string
    {
        $saved = match ($provider->value.'.'.$key) {
            'melipayamak.username' => $this->settings->smsSetting(StoreSettings::SMS_MELIPAYAMAK_USERNAME),
            'melipayamak.api_key' => $this->settings->smsSetting(StoreSettings::SMS_MELIPAYAMAK_API_KEY),
            'kavenegar.api_key' => $this->settings->smsSetting(StoreSettings::SMS_KAVENEGAR_API_KEY),
            default => null,
        };
        $value = $saved ?? (string) $this->setting($provider, $key);

        return $value === '' ? null : $value;
    }

    /**
     * The provider's pattern id for a template (the admin's, else .env), or null when it is not set up yet.
     */
    public function patternId(SmsTemplate $template, ?SmsProvider $provider = null): ?string
    {
        $provider = $this->provider($provider);
        $patternId = $this->settings->smsPatternId($provider->value, $template->value)
            ?? (string) $this->setting($provider, 'patterns.'.$template->value);

        return $patternId === '' ? null : $patternId;
    }

    /**
     * Super admins are alerted below this credit (rial): the admin's value, else SMS_LOW_CREDIT_BELOW.
     */
    public function lowCreditBelow(): int
    {
        return $this->settings->smsLowCreditBelow() ?? (int) config('sms.low_credit_below');
    }

    private function setting(SmsProvider $provider, string $key): mixed
    {
        return config('sms.providers.'.$provider->value.'.'.$key);
    }
}
