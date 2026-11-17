<?php

declare(strict_types=1);

namespace App\Modules\Settings\Database\Factories;

use App\Modules\Settings\Models\Pharmacist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pharmacist with a council number. States: responsible().
 *
 * @extends Factory<Pharmacist>
 */
final class PharmacistFactory extends Factory
{
    protected $model = Pharmacist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Pharmacist::COLUMN_FULL_NAME => 'دکتر مریم احمدی',
            Pharmacist::COLUMN_LICENSE_NUMBER => (string) fake()->numberBetween(10_000, 99_999),
            Pharmacist::COLUMN_BIO => 'دکترای داروسازی از دانشگاه علوم پزشکی تهران.',
            Pharmacist::COLUMN_IS_RESPONSIBLE => false,
            Pharmacist::COLUMN_SORT => 1,
        ];
    }

    public function responsible(): static
    {
        return $this->state(fn (array $attributes): array => [Pharmacist::COLUMN_IS_RESPONSIBLE => true]);
    }
}
