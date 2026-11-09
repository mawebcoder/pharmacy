<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Kinds of home page widget (roadmap D3.6, S13b). Each shows at most 6 items. S33 adds flash deals and bundles.
 */
enum HomeWidgetType: string implements HasLabel
{
    use LabelledEnum;

    case Slider = 'slider';
    case Collection = 'collection';
    case Brands = 'brands';
    case Categories = 'categories';
    case Tags = 'tags';
    case Trust = 'trust';

    public const int MAX_ITEMS = 6;

    public function label(): string
    {
        return match ($this) {
            self::Slider => 'اسلایدر بنر',
            self::Collection => 'ردیف محصولات یک کالکشن',
            self::Brands => 'برندها',
            self::Categories => 'دسته‌بندی‌ها',
            self::Tags => 'برچسب‌ها (نیازها و مواد مغذی)',
            self::Trust => 'اعتماد (مجوزها، داروساز، ارسال، مرجوعی)',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Slider => 'heroicon-o-photo',
            self::Collection => 'heroicon-o-rectangle-stack',
            self::Brands => 'heroicon-o-tag',
            self::Categories => 'heroicon-o-squares-2x2',
            self::Tags => 'heroicon-o-hashtag',
            self::Trust => 'heroicon-o-shield-check',
        };
    }

    /**
     * The settings key holding the widget's chosen item ids, in order.
     */
    public function itemsKey(): ?string
    {
        return match ($this) {
            self::Brands => 'brand_ids',
            self::Categories => 'category_ids',
            self::Tags => 'tag_ids',
            default => null,
        };
    }
}
