<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Factories;

use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Content\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
final class BannerFactory extends Factory
{
    protected $model = Banner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            Banner::COLUMN_TITLE => 'بنر '.fake()->unique()->numberBetween(1, 99_999),
            Banner::COLUMN_LINK_TYPE => BannerLinkType::BrandsList,
            Banner::COLUMN_IS_ACTIVE => true,
        ];
    }

    public function linkingTo(BannerLinkType $type, ?int $id = null, ?string $url = null): static
    {
        return $this->state(fn (array $attributes): array => [
            Banner::COLUMN_LINK_TYPE => $type,
            Banner::COLUMN_LINK_ID => $id,
            Banner::COLUMN_EXTERNAL_URL => $url,
        ]);
    }
}
