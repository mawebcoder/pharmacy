<?php

declare(strict_types=1);

namespace App\Modules\Profile\Livewire;

use App\Modules\Identity\Queries\CustomerInbox;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * «اعلان‌ها» in the account (owner, 2026-11-21): every message the customer was texted, newest first, unread ones
 * highlighted; opening one marks it read and goes to its order; «همه خوانده شد» marks them all.
 */
final class Notifications extends Component
{
    use WithPagination;

    public function open(string $notificationId, CustomerInbox $inbox): void
    {
        $url = $inbox->open($this->customerId(), $notificationId);

        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(CustomerInbox $inbox): void
    {
        $inbox->markAllRead($this->customerId());
    }

    public function render(CustomerInbox $inbox): View
    {
        return view('profile::livewire.notifications', [
            'notifications' => $inbox->page($this->customerId()),
            'unread' => $inbox->unreadCount($this->customerId()),
        ])->title('اعلان‌ها');
    }

    private function customerId(): int
    {
        return (int) auth('customer')->id();
    }
}
