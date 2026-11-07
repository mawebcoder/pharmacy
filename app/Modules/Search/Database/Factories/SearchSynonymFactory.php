<?php

declare(strict_types=1);

namespace App\Modules\Search\Database\Factories;

use App\Modules\Search\Models\SearchSynonym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchSynonym>
 */
final class SearchSynonymFactory extends Factory
{
    protected $model = SearchSynonym::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            SearchSynonym::COLUMN_TERM => 'واژه '.fake()->unique()->numberBetween(1, 99_999),
            SearchSynonym::COLUMN_SYNONYMS => ['term '.fake()->unique()->numberBetween(1, 99_999)],
        ];
    }
}
