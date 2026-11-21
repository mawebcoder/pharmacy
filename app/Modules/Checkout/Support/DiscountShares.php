<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Support;

use App\Modules\Cart\Data\CartLineData;

/**
 * An order discount shared over lines in proportion to their totals (whole rial; the remainder goes to the last line),
 * so each item knows what it really sold for and the shares add up to the discount: a coupon over the lines it covers,
 * or the discount staff type in for a panel order over all lines (owner, 2026-11-19).
 */
final class DiscountShares
{
    /**
     * @param  list<CartLineData>  $lines
     * @return array<int, int> variant id => discount
     */
    public static function over(int $discount, array $lines): array
    {
        if ($discount <= 0 || $lines === []) {
            return [];
        }

        $total = array_sum(array_map(fn (CartLineData $line): int => $line->lineTotal, $lines));
        $shares = [];
        $given = 0;

        foreach ($lines as $index => $line) {
            $share = $index === count($lines) - 1
                ? $discount - $given
                : intdiv($discount * $line->lineTotal, max(1, $total));
            $shares[$line->variant->variantId] = $share;
            $given += $share;
        }

        return $shares;
    }
}
