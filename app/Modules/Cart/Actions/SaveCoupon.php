<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\CouponData;
use App\Modules\Cart\Models\Coupon;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a coupon and its include lists (roadmap D3.4, S20).
 */
final class SaveCoupon
{
    #[\NoDiscard]
    public function __invoke(CouponData $data, ?Coupon $coupon = null): Coupon
    {
        return DB::transaction(function () use ($data, $coupon): Coupon {
            $coupon ??= new Coupon;
            $coupon->setCode($data->code)
                ->setType($data->type)
                ->setValue($data->value)
                ->setMaxDiscount($data->maxDiscount)
                ->setMinOrderAmount($data->minOrderAmount)
                ->setAppliesToSaleItems($data->appliesToSaleItems)
                ->setStartsAt($data->startsAt)
                ->setEndsAt($data->endsAt)
                ->setUsageLimitTotal($data->usageLimitTotal)
                ->setUsageLimitPerCustomer($data->usageLimitPerCustomer)
                ->setFirstOrderOnly($data->firstOrderOnly)
                ->setIsActive($data->isActive)
                ->save();

            $lists = ['coupon_categories' => $data->categoryIds, 'coupon_brands' => $data->brandIds, 'coupon_products' => $data->productIds];

            foreach ($lists as $table => $ids) {
                $column = Coupon::INCLUDE_LISTS[$table];
                DB::table($table)->where('coupon_id', $coupon->getId())->delete();
                DB::table($table)->insert(array_map(fn (int $id): array => ['coupon_id' => $coupon->getId(), $column => $id], $ids));
            }

            return $coupon;
        });
    }
}
