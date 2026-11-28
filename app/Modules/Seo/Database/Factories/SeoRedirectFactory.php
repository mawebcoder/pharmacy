<?php

declare(strict_types=1);

namespace App\Modules\Seo\Database\Factories;

use App\Modules\Seo\Enums\RedirectSource;
use App\Modules\Seo\Models\SeoRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A permanent redirect added by an admin, from an old product address to the products list.
 *
 * @extends Factory<SeoRedirect>
 */
final class SeoRedirectFactory extends Factory
{
    protected $model = SeoRedirect::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            SeoRedirect::COLUMN_FROM_PATH => '/products/old-'.fake()->unique()->slug(2),
            SeoRedirect::COLUMN_TO_PATH => '/products',
            SeoRedirect::COLUMN_STATUS_CODE => 301,
            SeoRedirect::COLUMN_SOURCE => RedirectSource::Admin,
        ];
    }
}
