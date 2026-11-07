<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * What kind of product it is (roadmap D3.1). OTC and Rx are medicines: their price is capped at the official
 * price, and the types listed in config('catalog.unsellable_product_types') cannot be activated for sale.
 */
enum ProductType: string implements HasLabel
{
    use LabelledEnum;

    case Cosmetic = 'cosmetic';
    case PersonalCare = 'personal_care';
    case Supplement = 'supplement';
    case Otc = 'otc';
    case Rx = 'rx';
    case MedicalDevice = 'medical_device';

    public function label(): string
    {
        return match ($this) {
            self::Cosmetic => 'آرایشی',
            self::PersonalCare => 'بهداشتی',
            self::Supplement => 'مکمل',
            self::Otc => 'داروی بدون نسخه (OTC)',
            self::Rx => 'داروی نسخه‌ای',
            self::MedicalDevice => 'تجهیزات پزشکی',
        };
    }

    /**
     * Medicines: the selling price may not exceed the official price printed on the box.
     */
    public function isMedicine(): bool
    {
        return $this === self::Otc || $this === self::Rx;
    }

    public function isSellable(): bool
    {
        /** @var list<string> $unsellable */
        $unsellable = config('catalog.unsellable_product_types', []);

        return ! in_array($this->value, $unsellable, true);
    }
}
