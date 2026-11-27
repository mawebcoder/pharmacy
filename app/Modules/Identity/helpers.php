<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\NotifyAdmin;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

if (! function_exists('notify_admin')) {
    /**
     * Keeps a notification for one admin under the admin panel's bell (e.g. a catalog import's result), with an
     * optional «مشاهده» link. $status is Filament's: success, warning, danger or info. Nothing when the admin is gone.
     */
    function notify_admin(int $adminId, string $title, ?string $body = null, string $status = 'info', ?string $url = null): void
    {
        $notification = Notification::make()->title($title)->body($body)->status($status);

        if ($url !== null) {
            $notification->actions([Action::make('view')->label('مشاهده')->url($url)->markAsRead()]);
        }

        app(NotifyAdmin::class)($adminId, $notification->toDatabase());
    }
}
