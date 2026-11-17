<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\Faq;
use Illuminate\Support\Facades\DB;

/**
 * Saves the order of the questions (drag and drop in «پرسش‌های متداول»); /faq shows each group in this order.
 */
final class ReorderFaqs
{
    /**
     * @param  list<int>  $faqIds  in the new order
     */
    public function __invoke(array $faqIds): void
    {
        $positions = array_flip($faqIds);

        DB::transaction(function () use ($positions): void {
            foreach (Faq::query()->whereKey(array_keys($positions))->get() as $faq) {
                $faq->setSort($positions[$faq->getId()] + 1)->save();
            }
        });
    }
}
