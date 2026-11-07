<?php

declare(strict_types=1);

namespace App\Modules\Search\Actions;

use App\Modules\Search\Data\SynonymImportResult;
use App\Modules\Search\Jobs\SyncSearchSynonyms;
use App\Modules\Search\Models\SearchSynonym;
use App\Modules\Search\Support\SynonymSpreadsheet;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Imports synonym groups from a spreadsheet (SynonymSpreadsheet). Existing phrases are merged — new synonyms are
 * added, existing ones kept — so importing the same file again changes nothing. Rows without a phrase or without
 * synonyms are skipped with the reason. The search index is updated once at the end.
 */
final class ImportSearchSynonyms
{
    #[\NoDiscard]
    public function __invoke(string $path, string $extension): SynonymImportResult
    {
        $rows = SynonymSpreadsheet::read($path, $extension);
        $created = 0;
        $updated = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, &$created, &$updated, &$skipped): void {
            foreach ($rows as $number => $cells) {
                $term = PersianNormalizer::letters(trim($cells[0] ?? ''));
                $synonyms = array_values(array_unique(array_filter(
                    array_map(fn (string $cell): string => PersianNormalizer::letters(trim($cell)), array_slice($cells, 1)),
                    fn (string $synonym): bool => $synonym !== '' && $synonym !== $term,
                )));

                if ($term === '') {
                    $skipped[$number] = 'عبارت خالی است';

                    continue;
                }

                if (mb_strlen($term) > 255) {
                    $skipped[$number] = 'عبارت بیش از ۲۵۵ نویسه است';

                    continue;
                }

                if ($synonyms === []) {
                    $skipped[$number] = 'هم‌معنی ندارد';

                    continue;
                }

                $synonym = SearchSynonym::query()->where(SearchSynonym::COLUMN_TERM, $term)->first();

                if (! $synonym instanceof SearchSynonym) {
                    (new SearchSynonym)->setTerm($term)->setSynonyms($synonyms)->save();
                    $created++;

                    continue;
                }

                $merged = array_values(array_unique([...$synonym->getSynonyms(), ...$synonyms]));

                if ($merged !== $synonym->getSynonyms()) {
                    $synonym->setSynonyms($merged)->save();
                    $updated++;
                }
            }
        });

        if ($created + $updated > 0) {
            SyncSearchSynonyms::dispatch()->afterCommit();
        }

        return new SynonymImportResult($created, $updated, $skipped);
    }
}
