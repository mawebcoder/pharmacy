<?php

declare(strict_types=1);

namespace App\Modules\Content\Enums;

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\Shared\Enums\LabelledEnum;

/**
 * The icons a menu item can show (owner, 2026-11-17): heroicons, the set the storefront and the admin both use, so
 * the value is the icon's name.
 */
enum MenuIcon: string implements HasLabel
{
    use LabelledEnum;

    case Grid = 'squares-2x2';
    case Tag = 'tag';
    case Bolt = 'bolt';
    case Percent = 'receipt-percent';
    case Sparkles = 'sparkles';
    case Gift = 'gift';
    case Heart = 'heart';
    case Star = 'star';
    case Store = 'building-storefront';
    case Info = 'information-circle';
    case Question = 'question-mark-circle';
    case Phone = 'phone';
    case Envelope = 'envelope';
    case Chat = 'chat-bubble-left-right';
    case Truck = 'truck';
    case CreditCard = 'credit-card';
    case Return = 'arrow-uturn-left';
    case ShieldCheck = 'shield-check';
    case Document = 'document-text';
    case Lock = 'lock-closed';
    case User = 'user-circle';
    case Home = 'home';
    case Beaker = 'beaker';
    case Map = 'map-pin';

    public function label(): string
    {
        return match ($this) {
            self::Grid => 'کاشی‌ها',
            self::Tag => 'برچسب',
            self::Bolt => 'صاعقه',
            self::Percent => 'درصد',
            self::Sparkles => 'درخشش',
            self::Gift => 'هدیه',
            self::Heart => 'قلب',
            self::Star => 'ستاره',
            self::Store => 'فروشگاه',
            self::Info => 'اطلاعات',
            self::Question => 'پرسش',
            self::Phone => 'تلفن',
            self::Envelope => 'پاکت نامه',
            self::Chat => 'گفتگو',
            self::Truck => 'کامیون',
            self::CreditCard => 'کارت بانکی',
            self::Return => 'بازگشت',
            self::ShieldCheck => 'سپر',
            self::Document => 'سند',
            self::Lock => 'قفل',
            self::User => 'کاربر',
            self::Home => 'خانه',
            self::Beaker => 'آزمایشگاه',
            self::Map => 'نشانی',
        };
    }
}
