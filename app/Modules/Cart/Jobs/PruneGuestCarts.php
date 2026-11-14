<?php

declare(strict_types=1);

namespace App\Modules\Cart\Jobs;

use App\Modules\Cart\Models\Cart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deletes guest carts whose 30 days have passed (roadmap D13.3, S19); customers' carts are kept.
 */
final class PruneGuestCarts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cart::query()
            ->whereNull(Cart::COLUMN_CUSTOMER_ID)
            ->where(Cart::COLUMN_EXPIRES_AT, '<', now())
            ->chunkById(500, fn ($carts) => Cart::query()->whereKey($carts->modelKeys())->delete());
    }
}
