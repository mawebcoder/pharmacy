<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Requests;

use App\Modules\Content\Data\FaqData;
use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Models\Faq;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Validation for a question on /faq (roadmap C5, S29); the Filament form applies the same baseRules().
 */
final class SaveFaqRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Enum>>
     */
    public static function baseRules(): array
    {
        return [
            Faq::COLUMN_GROUP => ['required', Rule::enum(FaqGroup::class)],
            Faq::COLUMN_QUESTION => ['required', 'string', 'max:255'],
            Faq::COLUMN_ANSWER => ['required', 'string', 'max:3000'],
            Faq::COLUMN_IS_ACTIVE => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('Create:Faq') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): FaqData
    {
        return FaqData::fromArray($this->validated());
    }
}
