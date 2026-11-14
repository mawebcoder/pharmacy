<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Active static pages with a short body. States: inactive().
 *
 * @extends Factory<Page>
 */
final class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Page::COLUMN_TITLE => 'راهنمای سفارش',
            Page::COLUMN_SLUG => 'guide-'.fake()->unique()->numberBetween(1, 999_999),
            Page::COLUMN_BODY => '<h2>ثبت سفارش</h2><p>کالا را به سبد اضافه کنید و پرداخت را انجام دهید.</p>',
            Page::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [Page::COLUMN_IS_ACTIVE => false]);
    }
}
