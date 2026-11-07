<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Shared\Support\SearchText;
use Meilisearch\Client;

/**
 * Replaces the synonyms of the Meilisearch product index (roadmap D21.5.6). Each group is mutual: every word or
 * phrase in it finds the others. Text is normalised like product text so «ویتامين دي» and «ویتامین دی» agree.
 * Used by the Search module whenever its synonyms change.
 */
final readonly class ApplySearchSynonyms
{
    public function __construct(private Client $meilisearch) {}

    /**
     * @param  list<list<string>>  $groups  e.g. [['ویتامین دی', 'ویتامین d', 'd3', 'vitamin d'], …]
     */
    public function __invoke(array $groups): void
    {
        /** @var array<non-empty-string, list<non-empty-string>> $synonyms phrases are non-empty (mutual() drops empty ones) */
        $synonyms = self::mutual($groups);

        $this->meilisearch->index((new Product)->indexableAs())->updateSynonyms($synonyms);
    }

    /**
     * @param  list<list<string>>  $groups
     * @return array<string, list<string>> every normalised phrase => the other phrases of its groups
     */
    public static function mutual(array $groups): array
    {
        $map = [];

        foreach ($groups as $group) {
            $phrases = array_values(array_unique(array_filter(array_map(SearchText::normalize(...), $group), fn (string $phrase): bool => $phrase !== '')));

            foreach ($phrases as $phrase) {
                $others = array_values(array_diff($phrases, [$phrase]));
                $map[$phrase] = array_values(array_unique([...($map[$phrase] ?? []), ...$others]));
            }
        }

        ksort($map);

        return $map;
    }
}
