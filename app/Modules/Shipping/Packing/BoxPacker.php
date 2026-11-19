<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Packing;

use App\Modules\Shipping\Data\BoxSpec;
use App\Modules\Shipping\Data\PackedParcel;
use App\Modules\Shipping\Data\PackItem;
use App\Modules\Shipping\Enums\ParcelContent;

/**
 * Packs items into boxes (roadmap S31 «بسته‌بندی», owner 2026-11-17/18). We have no reliable product sizes, so each
 * SKU says how many of it fit each box: a piece takes 1/that many of the box, and mixed pieces add up (6 of a SKU that
 * fits 12 and 3 of one that fits 6 fill one box). A box is full when either limit is reached first: its space, or its
 * own maximum weight with the box (never above Tapin's 6 kg per parcel).
 *
 * Fewest boxes per order first, then the smallest box for each: one box for everything when any box takes it (boxes
 * tried smallest first), else the fewest boxes found (largest pieces first, with an exact search for normal orders),
 * each shrunk to its smallest box. «ارسال جداگانه» pieces get their own box; a piece no box takes gets a parcel with no
 * box, for staff. A missing weight is packed as 300 g and its parcel marked an estimate.
 */
final readonly class BoxPacker
{
    public const int MAX_PARCEL_WEIGHT_G = 6000;

    public const int DEFAULT_WEIGHT_G = 300;

    /**
     * Up to this many pieces, the fewest boxes are searched for; beyond it, largest-first first fit decides.
     */
    private const int SEARCH_LIMIT = 16;

    private const float EPSILON = 1e-9;

    /**
     * @param  list<PackItem>  $items
     * @param  list<BoxSpec>  $boxes  the active boxes
     * @return list<PackedParcel>
     */
    public function pack(array $items, array $boxes): array
    {
        usort($boxes, fn (BoxSpec $a, BoxSpec $b): int => [$a->volume(), $a->emptyWeightG] <=> [$b->volume(), $b->emptyWeightG]);

        $alone = [];
        $together = [];

        foreach ($items as $item) {
            for ($piece = 0; $piece < $item->quantity; $piece++) {
                $unit = [
                    'item' => $item,
                    'weight' => $item->unitWeightG ?? self::DEFAULT_WEIGHT_G,
                    'estimate' => $item->unitWeightG === null,
                ];

                if ($item->shipSeparately) {
                    $alone[] = [$unit];
                } elseif ($this->smallestBox([$unit], $boxes) === null) {
                    // Nothing takes it: a parcel of its own with no box, for staff to handle.
                    $alone[] = [$unit];
                } else {
                    $together[] = $unit;
                }
            }
        }

        $groups = [];

        if ($together !== []) {
            $groups = $this->smallestBox($together, $boxes) !== null ? [$together] : $this->split($together, $boxes);
        }

        $parcels = [];

        foreach ([...$groups, ...$alone] as $group) {
            $parcels[] = $this->parcel($group, $this->smallestBox($group, $boxes));
        }

        return $parcels;
    }

    /**
     * Pieces too many for one box, split into the fewest boxes (owner, 2026-11-17: fewest boxes per order first).
     * Largest pieces first, first fit gives a start; for up to SEARCH_LIMIT pieces a search then looks for fewer boxes
     * and stops as soon as it reaches the lower bound (the space or weight the pieces need at the least).
     *
     * @param  list<array{item: PackItem, weight: int, estimate: bool}>  $pieces
     * @param  list<BoxSpec>  $boxes
     * @return list<list<array{item: PackItem, weight: int, estimate: bool}>>
     */
    private function split(array $pieces, array $boxes): array
    {
        $largest = $boxes[count($boxes) - 1];
        usort($pieces, fn (array $a, array $b): int => [$this->size($b, $boxes, $largest), $b['weight']] <=> [$this->size($a, $boxes, $largest), $a['weight']]);

        $best = $this->firstFit($pieces, $boxes);

        if (count($pieces) <= self::SEARCH_LIMIT && count($best) > $this->lowerBound($pieces, $boxes, $largest)) {
            $this->search($pieces, 0, [], $boxes, $best, $this->lowerBound($pieces, $boxes, $largest));
        }

        return $best;
    }

    /**
     * Each piece into the first group some box still takes it with, else a new group.
     *
     * @param  list<array{item: PackItem, weight: int, estimate: bool}>  $pieces
     * @param  list<BoxSpec>  $boxes
     * @return list<list<array{item: PackItem, weight: int, estimate: bool}>>
     */
    private function firstFit(array $pieces, array $boxes): array
    {
        $groups = [];

        foreach ($pieces as $piece) {
            foreach ($groups as $index => $group) {
                if ($this->smallestBox([...$group, $piece], $boxes) !== null) {
                    $groups[$index][] = $piece;

                    continue 2;
                }
            }

            $groups[] = [$piece];
        }

        return array_values($groups);
    }

    /**
     * Tries every placement of the remaining pieces, keeping the fewest groups found in $best.
     *
     * @param  list<array{item: PackItem, weight: int, estimate: bool}>  $pieces
     * @param  list<list<array{item: PackItem, weight: int, estimate: bool}>>  $groups
     * @param  list<BoxSpec>  $boxes
     * @param  list<list<array{item: PackItem, weight: int, estimate: bool}>>  $best
     */
    private function search(array $pieces, int $next, array $groups, array $boxes, array &$best, int $lowerBound): bool
    {
        if ($next === count($pieces)) {
            if (count($groups) < count($best)) {
                $best = $groups;
            }

            return count($best) <= $lowerBound;
        }

        $piece = $pieces[$next];

        foreach ($groups as $index => $group) {
            if ($this->smallestBox([...$group, $piece], $boxes) !== null) {
                $groups[$index][] = $piece;

                if ($this->search($pieces, $next + 1, $groups, $boxes, $best, $lowerBound)) {
                    return true;
                }

                array_pop($groups[$index]);
            }
        }

        // A new group only while it can still beat the best found.
        if (count($groups) + 1 < count($best)) {
            $groups[] = [$piece];

            if ($this->search($pieces, $next + 1, $groups, $boxes, $best, $lowerBound)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The fewest boxes these pieces could need: by space in the largest box, and by weight in the box that takes the
     * most.
     *
     * @param  list<array{item: PackItem, weight: int, estimate: bool}>  $pieces
     * @param  list<BoxSpec>  $boxes
     */
    private function lowerBound(array $pieces, array $boxes, BoxSpec $largest): int
    {
        $space = array_sum(array_map(fn (array $piece): float => $this->size($piece, $boxes, $largest), $pieces));
        $mostWeight = 0;

        foreach ($boxes as $box) {
            $mostWeight = max($mostWeight, min($box->maxWeightG, self::MAX_PARCEL_WEIGHT_G) - $box->emptyWeightG);
        }

        $weight = array_sum(array_column($pieces, 'weight'));

        return max(1, (int) ceil($space - self::EPSILON), $mostWeight > 0 ? (int) ceil($weight / $mostWeight) : 1);
    }

    /**
     * How much of the largest box (or the largest box that takes it) one piece fills, for sorting.
     *
     * @param  array{item: PackItem, weight: int, estimate: bool}  $piece
     * @param  list<BoxSpec>  $boxes
     */
    private function size(array $piece, array $boxes, BoxSpec $largest): float
    {
        foreach (array_reverse($boxes) as $box) {
            $share = $piece['item']->share($box);

            if ($share !== null) {
                return $share * $box->volume() / $largest->volume();
            }
        }

        return 1.0;
    }

    /**
     * The smallest box that holds all these pieces within both limits, or null.
     *
     * @param  list<array{item: PackItem, weight: int, estimate: bool}>  $pieces
     * @param  list<BoxSpec>  $boxes  smallest first
     */
    private function smallestBox(array $pieces, array $boxes): ?BoxSpec
    {
        $weight = array_sum(array_column($pieces, 'weight'));

        foreach ($boxes as $box) {
            if ($box->emptyWeightG + $weight > min($box->maxWeightG, self::MAX_PARCEL_WEIGHT_G)) {
                continue;
            }

            $filled = 0.0;

            foreach ($pieces as $piece) {
                $share = $piece['item']->share($box);

                if ($share === null) {
                    continue 2;
                }

                $filled += $share;
            }

            if ($filled <= 1 + self::EPSILON) {
                return $box;
            }
        }

        return null;
    }

    /**
     * @param  list<array{item: PackItem, weight: int, estimate: bool}>  $pieces
     */
    private function parcel(array $pieces, ?BoxSpec $box): PackedParcel
    {
        $items = [];
        $weight = 0;
        $value = 0;
        $liquid = false;
        $estimate = false;
        $contentIds = [];

        foreach ($pieces as $piece) {
            $item = $piece['item'];
            $items[$item->key] ??= ['key' => $item->key, 'variant_id' => $item->variantId, 'title' => $item->title, 'quantity' => 0];
            $items[$item->key]['quantity']++;
            $weight += $piece['weight'];
            $value += $item->unitValue;
            $liquid = $liquid || $item->liquidOrFragile;
            $estimate = $estimate || $piece['estimate'];
            $contentIds[(string) $item->tipaxContentId] = $item->tipaxContentId;
        }

        // The Tipax content the parcel's products share, if they all name the same one (owner, 2026-11-19).
        $sharedContentId = count($contentIds) === 1 ? reset($contentIds) : null;

        return new PackedParcel(
            box: $box,
            items: array_values($items),
            itemsWeightG: $weight,
            totalWeightG: $weight + ($box === null ? 0 : $box->emptyWeightG),
            declaredValue: $value,
            content: $liquid ? ParcelContent::LiquidFragile : ParcelContent::General,
            isEstimate: $estimate,
            tipaxContentId: $sharedContentId,
        );
    }
}
