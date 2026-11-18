<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Enums\ContactMessageStatus;
use App\Modules\Content\Models\ContactMessage;

/**
 * Moves a contact message on (roadmap S29): opening a new one marks it read; staff mark it answered once they have
 * replied (by phone or email, outside the site). A message never goes back to new.
 */
final class ChangeContactMessageStatus
{
    public function __invoke(ContactMessage $message, ContactMessageStatus $status): void
    {
        if ($status === ContactMessageStatus::New || $message->getStatus() === $status) {
            return;
        }

        if ($status === ContactMessageStatus::Read && $message->getStatus() === ContactMessageStatus::Answered) {
            return;
        }

        $message->setStatus($status)
            ->setReadAt($message->getReadAt() ?? now())
            ->setAnsweredAt($status === ContactMessageStatus::Answered ? now() : $message->getAnsweredAt())
            ->save();
    }
}
