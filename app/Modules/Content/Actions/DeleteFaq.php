<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\Faq;

/**
 * Removes a question from /faq (roadmap S34).
 */
final class DeleteFaq
{
    public function __invoke(Faq $faq): void
    {
        $faq->delete();
    }
}
