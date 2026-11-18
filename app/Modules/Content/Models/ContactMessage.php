<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\ContactMessageFactory;
use App\Modules\Content\Enums\ContactMessageStatus;
use App\Modules\Content\Enums\ContactSubject;
use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A message sent from /contact (roadmap D3.6, S29). Staff read it in «پیام‌های تماس» and mark it answered; only the
 * status is logged, not what the customer wrote.
 */
final class ContactMessage extends Model implements HasActivityLogLabel
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_CUSTOMER_ID = 'customer_id';

    public const string COLUMN_NAME = 'name';

    public const string COLUMN_MOBILE = 'mobile';

    public const string COLUMN_EMAIL = 'email';

    public const string COLUMN_SUBJECT = 'subject';

    public const string COLUMN_BODY = 'body';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_IP_ADDRESS = 'ip_address';

    public const string COLUMN_READ_AT = 'read_at';

    public const string COLUMN_ANSWERED_AT = 'answered_at';

    public const string COLUMN_CREATED_AT = 'created_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getAttribute(self::COLUMN_CUSTOMER_ID);

        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $customerId): static
    {
        return $this->setAttribute(self::COLUMN_CUSTOMER_ID, $customerId);
    }

    public function getName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_NAME);
    }

    public function setName(string $name): static
    {
        return $this->setAttribute(self::COLUMN_NAME, $name);
    }

    public function getMobile(): string
    {
        return (string) $this->getAttribute(self::COLUMN_MOBILE);
    }

    public function setMobile(string $mobile): static
    {
        return $this->setAttribute(self::COLUMN_MOBILE, $mobile);
    }

    public function getEmail(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_EMAIL);

        return $value === null ? null : (string) $value;
    }

    public function setEmail(?string $email): static
    {
        return $this->setAttribute(self::COLUMN_EMAIL, $email);
    }

    public function getSubject(): ContactSubject
    {
        return $this->getAttribute(self::COLUMN_SUBJECT);
    }

    public function setSubject(ContactSubject $subject): static
    {
        return $this->setAttribute(self::COLUMN_SUBJECT, $subject);
    }

    public function getBody(): string
    {
        return (string) $this->getAttribute(self::COLUMN_BODY);
    }

    public function setBody(string $body): static
    {
        return $this->setAttribute(self::COLUMN_BODY, $body);
    }

    public function getStatus(): ContactMessageStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(ContactMessageStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    public function getIpAddress(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_IP_ADDRESS);

        return $value === null ? null : (string) $value;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        return $this->setAttribute(self::COLUMN_IP_ADDRESS, $ipAddress);
    }

    public function getReadAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_READ_AT);
    }

    public function setReadAt(?Carbon $readAt): static
    {
        return $this->setAttribute(self::COLUMN_READ_AT, $readAt);
    }

    public function getAnsweredAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_ANSWERED_AT);
    }

    public function setAnsweredAt(?Carbon $answeredAt): static
    {
        return $this->setAttribute(self::COLUMN_ANSWERED_AT, $answeredAt);
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_CREATED_AT);
    }

    public function activityLogLabel(): string
    {
        return 'پیام تماس: '.$this->getName();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly([self::COLUMN_STATUS])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_SUBJECT => ContactSubject::class,
            self::COLUMN_STATUS => ContactMessageStatus::class,
            self::COLUMN_READ_AT => 'datetime',
            self::COLUMN_ANSWERED_AT => 'datetime',
        ];
    }
}
