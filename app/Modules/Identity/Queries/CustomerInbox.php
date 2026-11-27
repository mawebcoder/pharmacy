<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Modules\Identity\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A customer's «اعلان‌ها» (owner, 2026-11-21): newest first, how many are unread, and marking them read. Only the
 * customer's own notifications are ever read or changed.
 */
final readonly class CustomerInbox
{
    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function page(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($customerId)->latest()->paginate($perPage);
    }

    public function unreadCount(int $customerId): int
    {
        return $this->query($customerId)->whereNull('read_at')->count();
    }

    /**
     * Marks one read and returns the page it is about, if any.
     */
    public function open(int $customerId, string $notificationId): ?string
    {
        $notification = $this->query($customerId)->find($notificationId);

        if (! $notification instanceof DatabaseNotification) {
            return null;
        }

        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function markAllRead(int $customerId): void
    {
        $this->query($customerId)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function query(int $customerId): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', (new Customer)->getMorphClass())
            ->where('notifiable_id', $customerId);
    }
}
