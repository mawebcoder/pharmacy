<?php

declare(strict_types=1);

namespace App\Modules\Content\Data;

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Models\Faq;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Input for a question on /faq: the question and the answer are plain text (tags stripped; Blade escapes them).
 */
final readonly class FaqData
{
    public function __construct(
        public FaqGroup $group,
        public string $question,
        public string $answer,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the faqs columns (Faq::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $group = $data[Faq::COLUMN_GROUP] ?? FaqGroup::Other;
        $text = static fn (string $key): string => PersianNormalizer::normalize(trim(strip_tags((string) ($data[$key] ?? ''))));

        return new self(
            group: $group instanceof FaqGroup ? $group : FaqGroup::from((string) $group),
            question: $text(Faq::COLUMN_QUESTION),
            answer: $text(Faq::COLUMN_ANSWER),
            isActive: (bool) ($data[Faq::COLUMN_IS_ACTIVE] ?? true),
        );
    }
}
