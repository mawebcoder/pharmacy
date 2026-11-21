<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Why units come back (roadmap S27, owner 2026-11-19), one per return row, with an optional note.
 */
enum ReturnReason: string implements HasLabel
{
    use LabelledEnum;

    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case Expiry = 'expiry';
    case Missing = 'missing';
    case ChangedMind = 'changed_mind';
    case SupplierUnavailable = 'supplier_unavailable';
    case Other = 'other';

    /**
     * The reasons a customer may pick when asking for a return (owner, 2026-11-19): not the supplier's stock.
     *
     * @return array<int|string, string>
     */
    public static function customerOptions(): array
    {
        $options = self::options();
        unset($options[self::SupplierUnavailable->value]);

        return $options;
    }

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'آسیب‌دیده',
            self::WrongItem => 'کالای اشتباه',
            self::Expiry => 'نزدیک به انقضا یا منقضی',
            self::Missing => 'ناقص یا کسری',
            self::ChangedMind => 'انصراف مشتری',
            self::SupplierUnavailable => 'ناموجود نزد تأمین‌کننده',
            self::Other => 'سایر',
        };
    }
}
