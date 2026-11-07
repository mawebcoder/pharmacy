<?php

declare(strict_types=1);

namespace App\Modules\Search\Data;

use App\Modules\Shared\Support\PersianDigits;

/**
 * What a synonyms import did: groups created and updated, and rows skipped with their reason.
 */
final readonly class SynonymImportResult
{
    /**
     * @param  array<int, string>  $skipped  row number => reason
     */
    public function __construct(
        public int $created,
        public int $updated,
        public array $skipped,
    ) {}

    public function summary(): string
    {
        $parts = [
            PersianDigits::toPersian($this->created).' گروه جدید',
            PersianDigits::toPersian($this->updated).' گروه به‌روز',
        ];

        if ($this->skipped !== []) {
            $parts[] = PersianDigits::toPersian(count($this->skipped)).' ردیف رد شد';
        }

        return implode('، ', $parts);
    }

    /**
     * The first skipped rows, e.g. "ردیف ۷: عبارت خالی است".
     */
    public function skippedDetails(int $limit = 5): string
    {
        return collect($this->skipped)
            ->take($limit)
            ->map(fn (string $reason, int $row): string => 'ردیف '.PersianDigits::toPersian($row).': '.$reason)
            ->implode('؛ ');
    }
}
