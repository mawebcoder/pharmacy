<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Actions\NotifySuperAdmins;
use App\Modules\Notifications\Enums\SmsProvider;
use App\Modules\Shared\Support\Toman;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

/**
 * Tells every active super admin, under the admin panel's bell, that the SMS panel's credit is low or used up (roadmap
 * D11.6, S37; owner 2026-11-27). At most once an hour, however many sends fail meanwhile.
 */
final readonly class AlertLowSmsCredit
{
    public const string CACHE_KEY = 'sms:low-credit-alerted';

    public function __construct(private NotifySuperAdmins $notifySuperAdmins) {}

    /**
     * @param  int|null  $creditRial  what is left, or null when a send was refused for lack of credit
     */
    public function __invoke(SmsProvider $provider, ?int $creditRial): void
    {
        if (! Cache::add(self::CACHE_KEY, true, now()->addHour())) {
            return;
        }

        ($this->notifySuperAdmins)(Notification::make()
            ->title($creditRial === null ? 'اعتبار پنل پیامک تمام شده است' : 'اعتبار پنل پیامک کم است')
            ->body($creditRial === null
                ? 'پیامک‌های '.$provider->label().' ارسال نمی‌شوند؛ پنل را شارژ کنید.'
                : 'مانده اعتبار '.$provider->label().': '.Toman::format($creditRial).'. پیش از تمام شدن، پنل را شارژ کنید.')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->danger()
            ->toDatabase());
    }
}
