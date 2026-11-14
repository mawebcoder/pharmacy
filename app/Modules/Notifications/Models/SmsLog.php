<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Notifications\Enums\SmsTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One SMS sent or tried (roadmap D11.6). The recipient is masked (0912***6789) and secret variables (sign-in codes)
 * are never stored. A log table itself, so it is not written to the activity log.
 */
final class SmsLog extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_PROVIDER = 'provider';

    public const string COLUMN_TEMPLATE = 'template';

    public const string COLUMN_RECIPIENT = 'recipient';

    public const string COLUMN_VARIABLES = 'variables';

    public const string COLUMN_PATTERN_ID = 'pattern_id';

    public const string COLUMN_IS_PRETEND = 'is_pretend';

    public const string COLUMN_SUCCESS = 'success';

    public const string COLUMN_PROVIDER_MESSAGE_ID = 'provider_message_id';

    public const string COLUMN_CODE = 'code';

    public const string COLUMN_ERROR = 'error';

    public const string COLUMN_DURATION_MS = 'duration_ms';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getProvider(): SmsProvider
    {
        return $this->getAttribute(self::COLUMN_PROVIDER);
    }

    public function setProvider(SmsProvider $provider): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER, $provider);
    }

    public function getTemplate(): SmsTemplate
    {
        return $this->getAttribute(self::COLUMN_TEMPLATE);
    }

    public function setTemplate(SmsTemplate $template): static
    {
        return $this->setAttribute(self::COLUMN_TEMPLATE, $template);
    }

    public function getRecipient(): string
    {
        return (string) $this->getAttribute(self::COLUMN_RECIPIENT);
    }

    public function setRecipient(string $recipient): static
    {
        return $this->setAttribute(self::COLUMN_RECIPIENT, $recipient);
    }

    /**
     * @return list<string>|null
     */
    public function getVariables(): ?array
    {
        return $this->getAttribute(self::COLUMN_VARIABLES);
    }

    /**
     * @param  list<string>|null  $variables
     */
    public function setVariables(?array $variables): static
    {
        return $this->setAttribute(self::COLUMN_VARIABLES, $variables);
    }

    public function getPatternId(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_PATTERN_ID);

        return $value === null ? null : (string) $value;
    }

    public function setPatternId(?string $patternId): static
    {
        return $this->setAttribute(self::COLUMN_PATTERN_ID, $patternId);
    }

    public function isPretend(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_IS_PRETEND);
    }

    public function setIsPretend(bool $isPretend): static
    {
        return $this->setAttribute(self::COLUMN_IS_PRETEND, $isPretend);
    }

    public function isSuccess(): bool
    {
        return (bool) $this->getAttribute(self::COLUMN_SUCCESS);
    }

    public function setSuccess(bool $success): static
    {
        return $this->setAttribute(self::COLUMN_SUCCESS, $success);
    }

    public function getProviderMessageId(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_PROVIDER_MESSAGE_ID);

        return $value === null ? null : (string) $value;
    }

    public function setProviderMessageId(?string $providerMessageId): static
    {
        return $this->setAttribute(self::COLUMN_PROVIDER_MESSAGE_ID, $providerMessageId);
    }

    public function getCode(): string
    {
        return (string) $this->getAttribute(self::COLUMN_CODE);
    }

    public function setCode(string $code): static
    {
        return $this->setAttribute(self::COLUMN_CODE, $code);
    }

    public function getError(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_ERROR);

        return $value === null ? null : (string) $value;
    }

    public function setError(?string $error): static
    {
        return $this->setAttribute(self::COLUMN_ERROR, $error);
    }

    public function getDurationMs(): int
    {
        return (int) $this->getAttribute(self::COLUMN_DURATION_MS);
    }

    public function setDurationMs(int $durationMs): static
    {
        return $this->setAttribute(self::COLUMN_DURATION_MS, $durationMs);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    /**
     * «09123456789» → «0912***6789».
     */
    public static function mask(string $mobile): string
    {
        return strlen($mobile) < 8 ? '***' : substr($mobile, 0, 4).'***'.substr($mobile, -4);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_PROVIDER => SmsProvider::class,
            self::COLUMN_TEMPLATE => SmsTemplate::class,
            self::COLUMN_VARIABLES => 'array',
            self::COLUMN_IS_PRETEND => 'boolean',
            self::COLUMN_SUCCESS => 'boolean',
            self::COLUMN_DURATION_MS => 'integer',
            self::COLUMN_CREATED_AT => 'datetime',
        ];
    }
}
