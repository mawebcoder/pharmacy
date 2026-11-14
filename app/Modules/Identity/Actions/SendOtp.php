<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\OtpRefused;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Notifications\Data\SmsMessageData;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Shared\Support\IranMobile;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sends a 5-digit sign-in code by SMS (roadmap D13.1, S17). While the mobile's last code is still valid no new one is
 * sent (OtpRefused::liveCodeExists, with the seconds left); otherwise 10 codes per day per mobile and 5 per hour per IP. Codes are random_int, unique among unexpired codes; only their keyed hash is stored and they are never logged; older codes for the mobile
 * are removed. The SMS goes out on the «sms» queue (SendSmsJob, encrypted). Returns the normalised mobile.
 */
final class SendOtp
{
    public const int CODE_TTL_SECONDS = 120;

    public const int PER_MOBILE_DAILY = 10;

    public const int PER_IP_HOURLY = 5;

    /**
     * @throws OtpRefused
     */
    #[\NoDiscard]
    public function __invoke(string $mobileInput, ?string $ip): string
    {
        $mobile = IranMobile::normalize($mobileInput) ?? throw OtpRefused::invalidMobile();

        $liveCode = OtpCode::query()
            ->where(OtpCode::COLUMN_MOBILE, $mobile)
            ->where(OtpCode::COLUMN_EXPIRES_AT, '>', now())
            ->latest(OtpCode::COLUMN_ID)
            ->first();

        if ($liveCode !== null) {
            throw OtpRefused::liveCodeExists((int) ceil(now()->diffInSeconds($liveCode->getExpiresAt())));
        }

        $limits = [
            ['otp:mobile-day:'.$mobile, self::PER_MOBILE_DAILY, 86_400],
            ['otp:ip:'.($ip ?? 'unknown'), self::PER_IP_HOURLY, 3_600],
        ];

        foreach ($limits as [$key, $maxAttempts]) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                throw OtpRefused::tooManyRequests();
            }
        }

        foreach ($limits as [$key, , $decaySeconds]) {
            RateLimiter::hit($key, $decaySeconds);
        }

        $code = $this->uniqueCode();

        OtpCode::query()->where(OtpCode::COLUMN_MOBILE, $mobile)->delete();

        (new OtpCode)
            ->setMobile($mobile)
            ->setCodeHash(OtpCode::hash($code))
            ->setPurpose(OtpCode::PURPOSE_LOGIN)
            ->setExpiresAt(now()->addSeconds(self::CODE_TTL_SECONDS))
            ->setAttempts(0)
            ->setIp($ip)
            ->save();

        SendSmsJob::dispatch(new SmsMessageData($mobile, SmsTemplate::Otp, [$code]));

        return $mobile;
    }

    /**
     * A random 5-digit code that no other unexpired code has.
     */
    private function uniqueCode(): string
    {
        do {
            $code = (string) random_int(10_000, 99_999);
        } while (OtpCode::isLive($code));

        return $code;
    }
}
