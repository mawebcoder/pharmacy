<?php

declare(strict_types=1);

namespace App\Modules\System\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One call to an external service (roadmap D0.4, D3.6). Not itself audited (it is a log); rows older than
 * config('integrations.log.retention_days') are pruned daily.
 */
#[Fillable([
    self::COLUMN_SERVICE, self::COLUMN_OPERATION, self::COLUMN_METHOD, self::COLUMN_URL, self::COLUMN_REQUEST,
    self::COLUMN_RESPONSE, self::COLUMN_HTTP_STATUS, self::COLUMN_DURATION_MS, self::COLUMN_CORRELATION_ID,
    self::COLUMN_ORDER_ID, self::COLUMN_ERROR, self::COLUMN_CREATED_AT, self::COLUMN_UPDATED_AT,
])]
final class IntegrationLog extends Model
{
    use MassPrunable;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_SERVICE = 'service';

    public const string COLUMN_OPERATION = 'operation';

    public const string COLUMN_METHOD = 'method';

    public const string COLUMN_URL = 'url';

    public const string COLUMN_REQUEST = 'request';

    public const string COLUMN_RESPONSE = 'response';

    public const string COLUMN_HTTP_STATUS = 'http_status';

    public const string COLUMN_DURATION_MS = 'duration_ms';

    public const string COLUMN_CORRELATION_ID = 'correlation_id';

    public const string COLUMN_ORDER_ID = 'order_id';

    public const string COLUMN_ERROR = 'error';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getService(): string
    {
        return (string) $this->getAttribute(self::COLUMN_SERVICE);
    }

    public function setService(string $service): static
    {
        return $this->setAttribute(self::COLUMN_SERVICE, $service);
    }

    public function getOperation(): string
    {
        return (string) $this->getAttribute(self::COLUMN_OPERATION);
    }

    public function setOperation(string $operation): static
    {
        return $this->setAttribute(self::COLUMN_OPERATION, $operation);
    }

    public function getMethod(): string
    {
        return (string) $this->getAttribute(self::COLUMN_METHOD);
    }

    public function setMethod(string $method): static
    {
        return $this->setAttribute(self::COLUMN_METHOD, $method);
    }

    public function getUrl(): string
    {
        return (string) $this->getAttribute(self::COLUMN_URL);
    }

    public function setUrl(string $url): static
    {
        return $this->setAttribute(self::COLUMN_URL, $url);
    }

    /**
     * The redacted request (JSON: headers and body), decrypted.
     */
    public function getRequest(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_REQUEST);

        return $value === null ? null : (string) $value;
    }

    public function setRequest(?string $request): static
    {
        return $this->setAttribute(self::COLUMN_REQUEST, $request);
    }

    public function getResponse(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_RESPONSE);

        return $value === null ? null : (string) $value;
    }

    public function setResponse(?string $response): static
    {
        return $this->setAttribute(self::COLUMN_RESPONSE, $response);
    }

    public function getHttpStatus(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_HTTP_STATUS);

        return $value === null ? null : (int) $value;
    }

    public function setHttpStatus(?int $httpStatus): static
    {
        return $this->setAttribute(self::COLUMN_HTTP_STATUS, $httpStatus);
    }

    public function getDurationMs(): int
    {
        return (int) $this->getAttribute(self::COLUMN_DURATION_MS);
    }

    public function setDurationMs(int $durationMs): static
    {
        return $this->setAttribute(self::COLUMN_DURATION_MS, $durationMs);
    }

    public function getCorrelationId(): string
    {
        return (string) $this->getAttribute(self::COLUMN_CORRELATION_ID);
    }

    public function setCorrelationId(string $correlationId): static
    {
        return $this->setAttribute(self::COLUMN_CORRELATION_ID, $correlationId);
    }

    public function getOrderId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_ORDER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setOrderId(?int $orderId): static
    {
        return $this->setAttribute(self::COLUMN_ORDER_ID, $orderId);
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

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_UPDATED_AT);
    }

    /**
     * Whether the call failed: no response, or an HTTP error status.
     */
    public function isFailure(): bool
    {
        $status = $this->getHttpStatus();

        return $status === null || $status >= 400;
    }

    /**
     * @return Builder<IntegrationLog>
     */
    public function prunable(): Builder
    {
        return self::query()->where(self::COLUMN_CREATED_AT, '<', now()->subDays((int) config('integrations.log.retention_days')));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_REQUEST => 'encrypted',
            self::COLUMN_HTTP_STATUS => 'integer',
            self::COLUMN_DURATION_MS => 'integer',
            self::COLUMN_ORDER_ID => 'integer',
        ];
    }
}
