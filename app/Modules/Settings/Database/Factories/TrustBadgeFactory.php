<?php

declare(strict_types=1);

namespace App\Modules\Settings\Database\Factories;

use App\Modules\Settings\Models\TrustBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active e-Namad badge with its embed code as e-Namad hands it out. States: inactive().
 *
 * @extends Factory<TrustBadge>
 */
final class TrustBadgeFactory extends Factory
{
    protected $model = TrustBadge::class;

    public const string ENAMAD_CODE = "<a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=123456&Code=AbCdEf'><img referrerpolicy='origin' src='https://trustseal.enamad.ir/logo.aspx?id=123456&Code=AbCdEf' alt='' style='cursor:pointer' code='AbCdEf'></a>";

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            TrustBadge::COLUMN_TITLE => 'نماد اعتماد الکترونیکی',
            TrustBadge::COLUMN_EMBED_CODE => self::ENAMAD_CODE,
            TrustBadge::COLUMN_SORT => 1,
            TrustBadge::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [TrustBadge::COLUMN_IS_ACTIVE => false]);
    }
}
