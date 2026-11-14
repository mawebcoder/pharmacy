<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use App\Modules\Shared\Support\PersianDigits;

/**
 * What «سفارش دوباره» did: how many items went into the cart and which were left out.
 */
final readonly class ReorderResultData
{
    /**
     * @param  list<string>  $skipped  product names no longer available
     */
    public function __construct(
        public int $added,
        public array $skipped,
    ) {}

    /**
     * «۳ کالا به سبد اضافه شد؛ ۱ کالا دیگر موجود نیست.»
     */
    public function message(): string
    {
        $message = PersianDigits::toPersian($this->added).' کالا به سبد اضافه شد';

        return $this->skipped === [] ? $message.'.' : $message.'؛ '.PersianDigits::toPersian(count($this->skipped)).' کالا دیگر موجود نیست.';
    }
}
