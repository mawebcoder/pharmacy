<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\TrustBadge;
use Illuminate\Support\Facades\DB;

/**
 * Saves the order of the trust badges (drag and drop in «نمادهای اعتماد»); the footer shows them in this order.
 */
final class ReorderTrustBadges
{
    /**
     * @param  list<int>  $badgeIds  in the new order
     */
    public function __invoke(array $badgeIds): void
    {
        $positions = array_flip($badgeIds);

        DB::transaction(function () use ($positions): void {
            foreach (TrustBadge::query()->whereKey(array_keys($positions))->get() as $badge) {
                $badge->setSort($positions[$badge->getId()] + 1)->save();
            }
        });
    }
}
