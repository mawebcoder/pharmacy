<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * Where a parcel collected from someone stands (roadmap S27, owner 2026-11-19), from Tipax's parcel status ids
 * (`GET ParcelStatus`, read 2026-11-19): booked but not picked up, picked up and on its way, delivered, failed before
 * pickup (not confirmed, invalidated, cancelled), or sent back. A status we do not know counts as not picked up, so no
 * refund is let through on a guess.
 */
enum TipaxParcelStage: string implements HasLabel
{
    use LabelledEnum;

    case AwaitingPickup = 'awaiting_pickup';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case SentBack = 'sent_back';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPickup => 'در انتظار جمع‌آوری',
            self::PickedUp => 'جمع‌آوری شد',
            self::Delivered => 'تحویل انبار',
            self::Failed => 'ناموفق در تیپاکس',
            self::SentBack => 'عودت به فرستنده',
        };
    }

    public static function fromStatusId(?int $statusId): self
    {
        return match ($statusId) {
            38, 62, 39, 60 => self::PickedUp, // جمع آوری شد، در مسیر مبدا، تحویل به مرکز پردازش مبدا، رسیدن به شهر مقصد
            50 => self::Delivered, // تحویل به گیرنده
            33, 34, 57 => self::Failed, // عدم تایید نمایندگی مبدا، ابطال شد، لغو شد
            51, 52, 63 => self::SentBack, // عودت شد، برگشت خورد، مختومه عودتی
            default => self::AwaitingPickup, // ثبت اولیه، در انتظار تایید، در حال پردازش، در دست جمع آوری، or none yet
        };
    }

    /**
     * The parcels of one registration together: sent back if any is, delivered when all are, picked up once any left
     * the sender, failed when all failed, else awaiting pickup.
     *
     * @param  list<self>  $stages
     */
    public static function combined(array $stages): self
    {
        return match (true) {
            $stages === [] => self::AwaitingPickup,
            in_array(self::SentBack, $stages, true) => self::SentBack,
            array_unique(array_map(fn (self $stage): string => $stage->value, $stages)) === [self::Delivered->value] => self::Delivered,
            in_array(self::PickedUp, $stages, true) || in_array(self::Delivered, $stages, true) => self::PickedUp,
            array_unique(array_map(fn (self $stage): string => $stage->value, $stages)) === [self::Failed->value] => self::Failed,
            default => self::AwaitingPickup,
        };
    }

    /**
     * The parcels have left the sender: the registration can no longer be cancelled.
     */
    public function isPickedUp(): bool
    {
        return in_array($this, [self::PickedUp, self::Delivered, self::SentBack], true);
    }
}
