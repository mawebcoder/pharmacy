<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A one-time sign-in code (roadmap D13.1): only its keyed hash is stored; it expires after 2 minutes and allows
 * 5 tries. Short-lived security data, so it is not written to the activity log.
 */
final class OtpCode extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_MOBILE = 'mobile';

    public const string COLUMN_CODE_HASH = 'code_hash';

    public const string COLUMN_PURPOSE = 'purpose';

    public const string COLUMN_EXPIRES_AT = 'expires_at';

    public const string COLUMN_ATTEMPTS = 'attempts';

    public const string COLUMN_IP = 'ip';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string PURPOSE_LOGIN = 'login';

    public const UPDATED_AT = null;

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getMobile(): string
    {
        return (string) $this->getAttribute(self::COLUMN_MOBILE);
    }

    public function setMobile(string $mobile): static
    {
        return $this->setAttribute(self::COLUMN_MOBILE, $mobile);
    }

    public function getCodeHash(): string
    {
        return (string) $this->getAttribute(self::COLUMN_CODE_HASH);
    }

    public function setCodeHash(string $codeHash): static
    {
        return $this->setAttribute(self::COLUMN_CODE_HASH, $codeHash);
    }

    public function getPurpose(): string
    {
        return (string) $this->getAttribute(self::COLUMN_PURPOSE);
    }

    public function setPurpose(string $purpose): static
    {
        return $this->setAttribute(self::COLUMN_PURPOSE, $purpose);
    }

    public function getExpiresAt(): Carbon
    {
        return $this->getAttribute(self::COLUMN_EXPIRES_AT);
    }

    public function setExpiresAt(Carbon $expiresAt): static
    {
        return $this->setAttribute(self::COLUMN_EXPIRES_AT, $expiresAt);
    }

    public function getAttempts(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ATTEMPTS);
    }

    public function setAttempts(int $attempts): static
    {
        return $this->setAttribute(self::COLUMN_ATTEMPTS, $attempts);
    }

    public function getIp(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_IP);

        return $value === null ? null : (string) $value;
    }

    public function setIp(?string $ip): static
    {
        return $this->setAttribute(self::COLUMN_IP, $ip);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * The keyed hash of a code (HMAC with the app key), compared in constant time. It does not include the mobile,
     * so a new code can be checked against every live one (codes are unique while they are valid).
     */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * The local-only bypass code (config auth.otp.bypass), or null outside APP_ENV=local or when it is off.
     */
    public static function bypassCode(): ?string
    {
        $code = (string) config('auth.otp.bypass.code');

        return app()->isLocal() && config('auth.otp.bypass.enabled') && $code !== '' ? $code : null;
    }

    /**
     * Whether an unexpired code with this value exists, for any mobile.
     */
    public static function isLive(string $code): bool
    {
        return self::query()
            ->where(self::COLUMN_CODE_HASH, self::hash($code))
            ->where(self::COLUMN_EXPIRES_AT, '>', now())
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_EXPIRES_AT => 'datetime',
            self::COLUMN_ATTEMPTS => 'integer',
            self::COLUMN_CREATED_AT => 'datetime',
        ];
    }
}
