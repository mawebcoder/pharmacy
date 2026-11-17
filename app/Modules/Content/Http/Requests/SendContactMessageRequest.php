<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Data\ContactMessageData;
use App\Modules\Content\Enums\ContactSubject;
use App\Modules\Content\Models\ContactMessage;
use App\Modules\Shared\Rules\IranianMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Validation for the /contact form (roadmap C5, S34); the Livewire page applies the same baseRules().
 */
final class SendContactMessageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Enum|IranianMobile>>
     */
    public static function baseRules(): array
    {
        return [
            ContactMessage::COLUMN_NAME => ['required', 'string', 'min:2', 'max:80'],
            ContactMessage::COLUMN_MOBILE => ['required', 'string', 'max:20', new IranianMobile],
            ContactMessage::COLUMN_EMAIL => ['nullable', 'email', 'max:120'],
            ContactMessage::COLUMN_SUBJECT => ['required', Rule::enum(ContactSubject::class)],
            ContactMessage::COLUMN_BODY => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            ContactMessage::COLUMN_NAME => 'نام',
            ContactMessage::COLUMN_MOBILE => 'موبایل',
            ContactMessage::COLUMN_EMAIL => 'ایمیل',
            ContactMessage::COLUMN_SUBJECT => 'موضوع',
            ContactMessage::COLUMN_BODY => 'متن پیام',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::fieldLabels();
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): ContactMessageData
    {
        return ContactMessageData::fromArray($this->validated(), $this->user('customer')?->getAuthIdentifier() === null ? null : (int) $this->user('customer')->getAuthIdentifier(), $this->ip());
    }
}
