<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Models\Order;

/**
 * The admin's own note on an order (roadmap S24); never shown to the customer. The change is in the order's audit log.
 */
final class SaveAdminNote
{
    public function __invoke(Order $order, ?string $note): void
    {
        $note = trim((string) $note);

        $order->setAdminNote($note === '' ? null : $note)->save();
    }
}
