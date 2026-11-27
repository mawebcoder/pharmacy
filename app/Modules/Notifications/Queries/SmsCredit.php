<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Queries;

use App\Modules\Notifications\Support\SmsManager;
use Illuminate\Support\Facades\Cache;

/**
 * The SMS panel's credit for the admin dashboard (S37, owner 2026-11-27): asked from the provider at most every ten
 * minutes, again at once after «تنظیمات پیامک» is saved.
 */
final readonly class SmsCredit
{
    public const string CACHE_KEY = 'sms:credit';

    public function __construct(private SmsManager $sms) {}

    /**
     * @return array{provider: string, credit: int|null, threshold: int, pretend: bool, checked_at: string}
     */
    public function current(): array
    {
        /** @var array{provider: string, credit: int|null, threshold: int, pretend: bool, checked_at: string} */
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn (): array => [
            'provider' => $this->sms->provider()->label(),
            'credit' => $this->sms->isPretending() ? null : $this->sms->sender()->credit(),
            'threshold' => $this->sms->lowCreditBelow(),
            'pretend' => $this->sms->isPretending(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
