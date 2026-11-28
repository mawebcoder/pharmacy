<?php

declare(strict_types=1);

namespace App\Modules\Search\Console;

use App\Modules\Catalog\Queries\SearchVocabulary;
use App\Modules\Catalog\Search\ProductSearchIndex;
use App\Modules\Search\Models\SearchLog;
use App\Modules\Shared\Support\SearchText;
use Illuminate\Console\Command;
use Meilisearch\Client;

/**
 * How fast the instant search box answers (roadmap S39: p95 under 50 ms in Meilisearch). Sends the most searched
 * queries from the logs, or catalog words and their first letters (what someone mid-typing sends) when there are no
 * logs yet, to the products index as the box does, and reports the median, p95 and slowest, both round trip and
 * Meilisearch's own processing time.
 *
 *   php artisan search:benchmark --queries=200
 */
final class SearchBenchmarkCommand extends Command
{
    private const float TARGET_MS = 50.0;

    protected $signature = 'search:benchmark {--queries=200 : How many searches to send}';

    protected $description = 'Measure instant search speed in Meilisearch (median, p95, slowest)';

    public function handle(Client $meilisearch, SearchVocabulary $vocabulary): int
    {
        if (config('scout.driver') !== 'meilisearch') {
            $this->components->error('Search is not on Meilisearch here.');

            return self::FAILURE;
        }

        $queries = $this->queries($vocabulary, max(1, (int) $this->option('queries')));
        $index = $meilisearch->index(ProductSearchIndex::name());
        $roundTrips = [];
        $engine = [];

        foreach ($queries as $query) {
            $started = hrtime(true);
            $result = $index->search($query, ['limit' => 6, 'filter' => ['is_visible = true'], 'attributesToRetrieve' => ['id']]);
            $roundTrips[] = (hrtime(true) - $started) / 1e6;
            $engine[] = (float) $result->getProcessingTimeMs();
        }

        // queries() always returns at least one search, so both lists have a time.
        /** @var non-empty-list<float> $roundTrips */
        /** @var non-empty-list<float> $engine */
        $this->table(['', 'Median', 'p95', 'Slowest'], [
            ['Round trip (ms)', ...self::summary($roundTrips)],
            ['Meilisearch (ms)', ...self::summary($engine)],
        ]);

        $p95 = self::percentile($engine, 95);
        $this->components->info(sprintf('%d searches; Meilisearch p95 %.1f ms (target under %.0f ms): %s', count($queries), $p95, self::TARGET_MS, $p95 < self::TARGET_MS ? 'OK' : 'too slow'));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function queries(SearchVocabulary $vocabulary, int $count): array
    {
        $logged = SearchLog::query()
            ->selectRaw(SearchLog::COLUMN_NORMALIZED_QUERY.', COUNT(*) as searches')
            ->groupBy(SearchLog::COLUMN_NORMALIZED_QUERY)
            ->orderByDesc('searches')
            ->limit($count)
            ->pluck(SearchLog::COLUMN_NORMALIZED_QUERY)
            ->map(fn (mixed $query): string => (string) $query)
            ->filter()
            ->values()
            ->all();

        $words = $vocabulary->words();
        $typed = [...$words, ...array_map(fn (string $word): string => mb_substr($word, 0, 3), $words)];
        $pool = $logged !== [] ? $logged : array_values(array_unique(array_map(SearchText::normalize(...), $typed)));

        if ($pool === []) {
            return ['a'];
        }

        $queries = [];

        for ($i = 0; $i < $count; $i++) {
            $queries[] = $pool[$i % count($pool)];
        }

        return $queries;
    }

    /**
     * @param  non-empty-list<float>  $times
     * @return list<string>
     */
    private static function summary(array $times): array
    {
        return array_map(fn (float $value): string => sprintf('%.1f', $value), [self::percentile($times, 50), self::percentile($times, 95), max($times)]);
    }

    /**
     * @param  non-empty-list<float>  $times
     */
    private static function percentile(array $times, int $percent): float
    {
        sort($times);

        return $times[(int) min(count($times) - 1, max(0, ceil($percent / 100 * count($times)) - 1))];
    }
}
