<?php

declare(strict_types=1);

namespace App\Modules\Content\Data;

use App\Modules\Content\Enums\ContactSubject;
use App\Modules\Content\Models\ContactMessage;
use App\Modules\Shared\Support\IranMobile;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * A message typed on /contact: plain text (tags stripped), the mobile normalised to 09…, an empty email is none.
 */
final readonly class ContactMessageData
{
    public function __construct(
        public string $name,
        public string $mobile,
        public ?string $email,
        public ContactSubject $subject,
        public string $body,
        public ?int $customerId,
        public ?string $ipAddress,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the contact_messages columns (ContactMessage::COLUMN_*)
     */
    public static function fromArray(array $data, ?int $customerId = null, ?string $ipAddress = null): self
    {
        $text = static fn (string $key): string => PersianNormalizer::normalize(trim(strip_tags((string) ($data[$key] ?? ''))));
        $subject = $data[ContactMessage::COLUMN_SUBJECT] ?? ContactSubject::Other;
        $email = mb_strtolower(trim((string) ($data[ContactMessage::COLUMN_EMAIL] ?? '')));
        $mobile = (string) ($data[ContactMessage::COLUMN_MOBILE] ?? '');

        return new self(
            name: $text(ContactMessage::COLUMN_NAME),
            mobile: IranMobile::normalize($mobile) ?? $mobile,
            email: $email === '' ? null : $email,
            subject: $subject instanceof ContactSubject ? $subject : ContactSubject::from((string) $subject),
            body: $text(ContactMessage::COLUMN_BODY),
            customerId: $customerId,
            ipAddress: $ipAddress,
        );
    }
}
