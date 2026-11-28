<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Support\SearchQueryText;
use App\Modules\Shared\Support\SearchText;
use Illuminate\Support\Facades\Cache;

/**
 * «منظورتان … بود؟» (roadmap S39, owner 2026-11-27: from catalog words): the words of brand, category, subcategory and
 * tag names and of product names, normalised like the index and cached for an hour. A search that found nothing gets
 * each unknown word replaced by the closest known one, within a spelling distance that grows with the word's length.
 */
final readonly class SearchVocabulary
{
    private const string CACHE_KEY = 'catalog:search-vocabulary';

    private const int MIN_WORD_LENGTH = 3;

    public function __construct(private SearchQueryText $queryText) {}

    /**
     * The corrected search, or null when every word is known or none has a close match.
     */
    public function suggest(string $text): ?string
    {
        $query = $this->queryText->clean($text);

        if ($query === '') {
            return null;
        }

        $vocabulary = $this->words();
        $known = array_flip($vocabulary);
        $changed = false;
        $words = [];

        foreach (explode(' ', $query) as $word) {
            $closest = isset($known[$word]) || mb_strlen($word) < self::MIN_WORD_LENGTH || is_numeric($word) ? null : self::closest($word, $vocabulary);
            $words[] = $closest ?? $word;
            $changed = $changed || $closest !== null;
        }

        return $changed ? implode(' ', $words) : null;
    }

    /**
     * @return list<string>
     */
    public function words(): array
    {
        /** @var list<string> */
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function (): array {
            $names = [
                ...Brand::query()->active()->get([Brand::COLUMN_NAME_FA, Brand::COLUMN_NAME_EN])->flatMap(fn (Brand $brand): array => [$brand->getNameFa(), (string) $brand->getNameEn()])->all(),
                ...Category::query()->get([Category::COLUMN_NAME_FA, Category::COLUMN_NAME_EN])->flatMap(fn (Category $category): array => [$category->getNameFa(), (string) $category->getNameEn()])->all(),
                ...Subcategory::query()->get([Subcategory::COLUMN_NAME_FA, Subcategory::COLUMN_NAME_EN])->flatMap(fn (Subcategory $subcategory): array => [$subcategory->getNameFa(), (string) $subcategory->getNameEn()])->all(),
                ...Tag::query()->get([Tag::COLUMN_NAME_FA, Tag::COLUMN_NAME_EN])->flatMap(fn (Tag $tag): array => [$tag->getNameFa(), (string) $tag->getNameEn()])->all(),
                ...Product::query()->sellable()->get([Product::COLUMN_NAME_FA, Product::COLUMN_NAME_EN])->flatMap(fn (Product $product): array => [$product->getNameFa(), (string) $product->getNameEn()])->all(),
            ];

            $words = SearchText::words(implode(' ', $names));

            return array_values(array_filter($words, fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH && ! is_numeric($word)));
        });
    }

    /**
     * The known word nearest to this one: 1 edit for words up to 4 letters, 2 up to 8, 3 beyond; the shortest distance
     * wins, the first on a tie.
     *
     * @param  list<string>  $vocabulary
     */
    private static function closest(string $word, array $vocabulary): ?string
    {
        $length = mb_strlen($word);
        $allowed = $length <= 4 ? 1 : ($length <= 8 ? 2 : 3);
        $best = null;
        $bestDistance = $allowed + 1;

        foreach ($vocabulary as $candidate) {
            if (abs(mb_strlen($candidate) - $length) > $allowed) {
                continue;
            }

            $distance = self::distance($word, $candidate);

            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$candidate, $distance];
            }
        }

        return $best;
    }

    /**
     * Levenshtein distance counted in letters, not bytes (Persian letters take two bytes in UTF-8).
     */
    private static function distance(string $a, string $b): int
    {
        $map = [];
        $toBytes = function (string $text) use (&$map): string {
            $bytes = '';

            foreach (mb_str_split($text) as $letter) {
                $map[$letter] ??= chr(count($map) % 256);
                $bytes .= $map[$letter];
            }

            return $bytes;
        };

        return levenshtein($toBytes($a), $toBytes($b));
    }
}
