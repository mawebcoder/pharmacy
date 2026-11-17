<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\ContactMessageStatus;
use App\Modules\Content\Enums\ContactSubject;
use App\Modules\Content\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A new message about an order. States: read(), answered().
 *
 * @extends Factory<ContactMessage>
 */
final class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ContactMessage::COLUMN_NAME => 'سارا محمدی',
            ContactMessage::COLUMN_MOBILE => '0912'.fake()->numerify('#######'),
            ContactMessage::COLUMN_SUBJECT => ContactSubject::Order,
            ContactMessage::COLUMN_BODY => 'سفارش من هنوز ارسال نشده است؛ لطفاً پیگیری کنید.',
            ContactMessage::COLUMN_STATUS => ContactMessageStatus::New,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [ContactMessage::COLUMN_STATUS => ContactMessageStatus::Read, ContactMessage::COLUMN_READ_AT => now()]);
    }

    public function answered(): static
    {
        return $this->state(fn (array $attributes): array => [ContactMessage::COLUMN_STATUS => ContactMessageStatus::Answered, ContactMessage::COLUMN_READ_AT => now(), ContactMessage::COLUMN_ANSWERED_AT => now()]);
    }
}
