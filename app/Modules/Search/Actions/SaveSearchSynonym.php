<?php

declare(strict_types=1);

namespace App\Modules\Search\Actions;

use App\Modules\Search\Jobs\SyncSearchSynonyms;
use App\Modules\Search\Models\SearchSynonym;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * Creates or updates a synonym group and sends all synonyms to the search index.
 */
final class SaveSearchSynonym
{
    /**
     * @param  list<string>  $synonyms
     */
    #[\NoDiscard]
    public function __invoke(string $term, array $synonyms, ?SearchSynonym $synonym = null): SearchSynonym
    {
        $clean = fn (string $text): string => PersianNormalizer::letters(trim($text));
        $term = $clean($term);

        $synonym ??= new SearchSynonym;
        $synonym->setTerm($term)
            ->setSynonyms(array_values(array_unique(array_filter(array_map($clean, $synonyms), fn (string $text): bool => $text !== '' && $text !== $term))))
            ->save();

        SyncSearchSynonyms::dispatch()->afterCommit();

        return $synonym;
    }
}
