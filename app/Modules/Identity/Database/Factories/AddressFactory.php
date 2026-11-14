<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\Address;
use App\Modules\Identity\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Addresses in the first city of the database unless inCity() is given (create cities with the Locations factories
 * first). States: inCity(), default().
 *
 * @extends Factory<Address>
 */
final class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Address::COLUMN_CUSTOMER_ID => Customer::factory(),
            Address::COLUMN_TITLE => 'خانه',
            Address::COLUMN_RECEIVER_NAME => 'سارا رضایی',
            Address::COLUMN_RECEIVER_MOBILE => '0912'.fake()->numerify('#######'),
            Address::COLUMN_PROVINCE_ID => fn (): int => $this->firstCity()['province_id'],
            Address::COLUMN_CITY_ID => fn (): int => $this->firstCity()['id'],
            Address::COLUMN_FULL_ADDRESS => 'خیابان ولیعصر، کوچه نسترن',
            Address::COLUMN_PLAQUE => (string) fake()->numberBetween(1, 200),
            Address::COLUMN_UNIT => (string) fake()->numberBetween(1, 20),
            Address::COLUMN_FLOOR => (string) fake()->numberBetween(1, 10),
            Address::COLUMN_POSTAL_CODE => fake()->numerify('1#########'),
            Address::COLUMN_IS_DEFAULT => false,
        ];
    }

    public function inCity(int $provinceId, int $cityId): static
    {
        return $this->state(fn (array $attributes): array => [Address::COLUMN_PROVINCE_ID => $provinceId, Address::COLUMN_CITY_ID => $cityId]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [Address::COLUMN_IS_DEFAULT => true]);
    }

    /**
     * @return array{id: int, province_id: int}
     */
    private function firstCity(): array
    {
        $city = DB::table('cities')->orderBy('id')->first(['id', 'province_id'])
            ?? throw new RuntimeException('Create a city (Locations factories) before an address.');

        return ['id' => (int) $city->id, 'province_id' => (int) $city->province_id];
    }
}
