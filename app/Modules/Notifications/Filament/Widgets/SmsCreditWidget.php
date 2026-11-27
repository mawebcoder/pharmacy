<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Filament\Widgets;

use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Notifications\Queries\SmsCredit;
use App\Modules\Shared\Support\JalaliDate;
use App\Modules\Shared\Support\Toman;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * «اعتبار پیامک» on the admin dashboard (S37, owner 2026-11-27), for super admins: what is left in the SMS panel, red
 * below the low-credit threshold.
 */
final class SmsCreditWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth('admin')->user()?->can(NotificationsServiceProvider::MANAGE_SMS) ?? false;
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $credit = app(SmsCredit::class)->current();
        $checkedAt = 'به‌روز شده '.JalaliDate::display(Carbon::parse($credit['checked_at']), 'H:i');

        if ($credit['pretend']) {
            return [Stat::make('اعتبار پیامک', 'حالت آزمایشی')
                ->description('SMS_PRETEND روشن است؛ پیامکی ارسال نمی‌شود.')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('gray')];
        }

        if ($credit['credit'] === null) {
            return [Stat::make('اعتبار پیامک', 'نامشخص')
                ->description($credit['provider'].' پاسخ نداد؛ تنظیمات پیامک را بررسی کنید. '.$checkedAt)
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('warning')];
        }

        $isLow = $credit['credit'] < $credit['threshold'];

        return [Stat::make('اعتبار پیامک', Toman::format($credit['credit']))
            ->description($credit['provider'].($isLow ? '؛ کمتر از '.Toman::format($credit['threshold']).'، پنل را شارژ کنید. ' : '. ').$checkedAt)
            ->descriptionIcon($isLow ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color($isLow ? 'danger' : 'success')];
    }
}
