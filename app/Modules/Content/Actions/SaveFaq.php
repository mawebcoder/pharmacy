<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Data\FaqData;
use App\Modules\Content\Models\Faq;

/**
 * Adds a question to /faq (at the end of its group) or changes one (roadmap S29). A question moved to another group
 * goes to that group's end.
 */
final class SaveFaq
{
    #[\NoDiscard]
    public function __invoke(FaqData $data, ?Faq $faq = null): Faq
    {
        $faq ??= new Faq;

        if (! $faq->exists || $faq->getGroup() !== $data->group) {
            $faq->setSort((int) Faq::query()->where(Faq::COLUMN_GROUP, $data->group)->max(Faq::COLUMN_SORT) + 1);
        }

        $faq->setGroup($data->group)
            ->setQuestion($data->question)
            ->setAnswer($data->answer)
            ->setIsActive($data->isActive)
            ->save();

        return $faq;
    }
}
