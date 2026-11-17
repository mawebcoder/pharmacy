<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\FaqGroup;
use App\Modules\Content\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active question about shipping. States: inactive().
 *
 * @extends Factory<Faq>
 */
final class FaqFactory extends Factory
{
    protected $model = Faq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Faq::COLUMN_GROUP => FaqGroup::OrderAndShipping,
            Faq::COLUMN_QUESTION => 'سفارش من چند روزه می‌رسد؟ '.fake()->unique()->numberBetween(1, 999_999),
            Faq::COLUMN_ANSWER => 'سفارش‌های تهران یک تا دو روز کاری و شهرهای دیگر دو تا پنج روز کاری می‌رسند.',
            Faq::COLUMN_SORT => fake()->numberBetween(1, 100),
            Faq::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [Faq::COLUMN_IS_ACTIVE => false]);
    }
}
