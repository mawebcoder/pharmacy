<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Sort orders of product lists (roadmap D21.2 item 5). "Relevant" only applies to text searches.
 */
enum ListingSort: string implements HasLabel
{
    use LabelledEnum;

    case Relevant = 'relevant';
    case BestSelling = 'best_selling';
    case Newest = 'newest';
    case Cheapest = 'cheapest';
    case MostExpensive = 'expensive';
    case BiggestDiscount = 'discount';

    public function label(): string
    {
        return match ($this) {
            self::Relevant => 'مرتبط‌ترین',
            self::BestSelling => 'پرفروش‌ترین',
            self::Newest => 'جدیدترین',
            self::Cheapest => 'ارزان‌ترین',
            self::MostExpensive => 'گران‌ترین',
            self::BiggestDiscount => 'بیشترین تخفیف',
        };
    }

    /**
     * Meilisearch sort rules; relevance needs none.
     *
     * @return list<string>
     */
    public function meilisearchSort(): array
    {
        return match ($this) {
            self::Relevant => [],
            // sales_30d is 0 until orders exist (S20), so newest decides.
            self::BestSelling => ['sales_30d:desc', 'created_at:desc'],
            self::Newest => ['created_at:desc'],
            self::Cheapest => ['price:asc'],
            self::MostExpensive => ['price:desc'],
            self::BiggestDiscount => ['discount_pct:desc'],
        };
    }

    /**
     * The orders a list offers: relevance only when a text is searched.
     *
     * @return list<self>
     */
    public static function available(bool $isSearch): array
    {
        return array_values(array_filter(self::cases(), fn (self $sort): bool => $isSearch || $sort !== self::Relevant));
    }
}
