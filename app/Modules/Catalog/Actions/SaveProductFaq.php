<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductFaq;

/**
 * Adds a question to a product (at the end of its FAQ list) or updates one.
 */
final class SaveProductFaq
{
    #[\NoDiscard]
    public function __invoke(Product $product, string $question, string $answer, ?ProductFaq $faq = null): ProductFaq
    {
        $faq ??= (new ProductFaq)
            ->setProductId($product->getId())
            ->setSort((int) $product->faqs()->max(ProductFaq::COLUMN_SORT) + 1);

        $faq->setQuestion(trim($question))->setAnswer(trim($answer))->save();

        return $faq;
    }
}
