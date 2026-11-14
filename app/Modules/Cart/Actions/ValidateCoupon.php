<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Contracts\CustomerOrderHistory;
use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartLineData;
use App\Modules\Cart\Enums\CouponType;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Cart\Models\CouponUsage;
use App\Modules\Catalog\Queries\CouponCatalog;
use Illuminate\Support\Carbon;

/**
 * Checks a coupon against a cart and works out its discount (roadmap D3.4, S20); the cart page and checkout (S25)
 * both use it, so the rules live in one place. It only reads: checkout works the discount out on the fly. Limits count
 * uses that are used or reserved (not expired), never the checkout log. In order: active and not deleted, date window, a signed-in customer,
 * first order only, total and per-customer limits, the items it covers, the minimum on those items, then the
 * discount, rounded down to whole toman.
 *
 * Covered items: lines that can be bought; haraj (sale-priced) lines only when the coupon allows them; and, when any
 * include list is filled, lines whose product is listed, whose brand is listed, or that sit in a listed category or
 * below it (any list may match). With every list empty the whole cart is covered.
 */
final readonly class ValidateCoupon
{
    public function __construct(
        private CouponCatalog $catalog,
        private CustomerOrderHistory $orders,
    ) {}

    /**
     * @param  list<CartLineData>  $lines
     *
     * @throws CouponRefused
     */
    #[\NoDiscard]
    public function __invoke(Coupon $coupon, array $lines, ?int $customerId): AppliedCouponData
    {
        $this->checkRules($coupon, $customerId);

        $coveredLines = $this->coveredLines($coupon, $lines);
        $covered = array_sum(array_map(fn (CartLineData $line): int => $line->lineTotal, $coveredLines));
        $isLimited = $this->hasIncludeLists($coupon);

        if ($covered === 0) {
            throw $lines === [] ? CouponRefused::cartEmpty() : CouponRefused::noCoveredItems();
        }

        if ($covered < $coupon->getMinOrderAmount()) {
            throw CouponRefused::belowMinimum($coupon->getMinOrderAmount(), $covered, $isLimited);
        }

        return new AppliedCouponData(
            couponId: $coupon->getId(),
            code: $coupon->getCode(),
            discount: $this->discount($coupon, $covered),
            freeShipping: $coupon->getType() === CouponType::FreeShipping,
            coveredSubtotal: $covered,
            coveredVariantIds: array_map(fn (CartLineData $line): int => $line->variant->variantId, $coveredLines),
        );
    }

    /**
     * The rules that do not depend on the cart's items (also re-checked under a lock when the coupon is reserved).
     *
     * @throws CouponRefused
     */
    public function checkRules(Coupon $coupon, ?int $customerId): void
    {
        if (! $coupon->isActive() || $coupon->trashed()) {
            throw CouponRefused::notFound();
        }

        if ($coupon->getStartsAt() instanceof Carbon && $coupon->getStartsAt()->isFuture()) {
            throw CouponRefused::notStarted($coupon->getStartsAt());
        }

        if ($coupon->getEndsAt() instanceof Carbon && $coupon->getEndsAt()->isPast()) {
            throw CouponRefused::expired();
        }

        if ($customerId === null) {
            throw CouponRefused::signInRequired();
        }

        if ($coupon->isFirstOrderOnly() && $this->orders->hasPlacedOrder($customerId)) {
            throw CouponRefused::firstOrderOnly();
        }

        if ($coupon->getUsageLimitTotal() !== null && $coupon->usages()->countingTowardLimits()->count() >= $coupon->getUsageLimitTotal()) {
            throw CouponRefused::exhausted();
        }

        if ($coupon->getUsageLimitPerCustomer() !== null
            && $coupon->usages()->countingTowardLimits()->where(CouponUsage::COLUMN_CUSTOMER_ID, $customerId)->count() >= $coupon->getUsageLimitPerCustomer()) {
            throw CouponRefused::alreadyUsed();
        }
    }

    /**
     * @param  list<CartLineData>  $lines
     * @return list<CartLineData>
     */
    private function coveredLines(Coupon $coupon, array $lines): array
    {
        $lines = array_values(array_filter(
            $lines,
            fn (CartLineData $line): bool => $line->isAvailable() && ($coupon->appliesToSaleItems() || ! $line->variant->isOnSale),
        ));

        if ($lines === [] || ! $this->hasIncludeLists($coupon)) {
            return $lines;
        }

        $productIds = $coupon->includedIds('coupon_products');
        $brandIds = $coupon->includedIds('coupon_brands');
        $categoryIds = $this->catalog->withSubcategories($coupon->includedIds('coupon_categories'));
        $groups = $this->catalog->productGroups(array_map(fn (CartLineData $line): int => $line->variant->productId, $lines));
        $covered = [];

        foreach ($lines as $line) {
            $productId = $line->variant->productId;
            $group = $groups[$productId] ?? ['brandId' => 0, 'categoryIds' => []];

            if (in_array($productId, $productIds, true)
                || in_array($group['brandId'], $brandIds, true)
                || array_intersect($group['categoryIds'], $categoryIds) !== []) {
                $covered[] = $line;
            }
        }

        return $covered;
    }

    private function hasIncludeLists(Coupon $coupon): bool
    {
        foreach (array_keys(Coupon::INCLUDE_LISTS) as $table) {
            if ($coupon->includedIds($table) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Percent of the covered amount (capped by max_discount), or the fixed amount (never more than the covered
     * amount); free shipping gives no item discount. Rounded down to whole toman (10 rial).
     */
    private function discount(Coupon $coupon, int $covered): int
    {
        $discount = match ($coupon->getType()) {
            CouponType::Percent => intdiv($covered * min(100, $coupon->getValue()), 100),
            CouponType::Fixed => min($coupon->getValue(), $covered),
            CouponType::FreeShipping => 0,
        };

        if ($coupon->getType() === CouponType::Percent && $coupon->getMaxDiscount() !== null) {
            $discount = min($discount, $coupon->getMaxDiscount());
        }

        return intdiv($discount, 10) * 10;
    }
}
