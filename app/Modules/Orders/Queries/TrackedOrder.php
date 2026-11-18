<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Models\Order;
use App\Modules\Shared\Support\IranMobile;
use App\Modules\Shared\Support\PersianNormalizer;

/**
 * «پیگیری سفارش» without signing in (roadmap S24): an order found by its number together with the mobile of the
 * customer or the receiver, both from the order's copies. Anything else finds nothing, so numbers cannot be probed.
 */
final class TrackedOrder
{
    public function find(string $number, string $mobile): ?Order
    {
        $number = strtoupper(trim(PersianNormalizer::digits($number)));
        $mobile = IranMobile::normalize(PersianNormalizer::digits($mobile));

        if ($number === '' || $mobile === null) {
            return null;
        }

        return Order::query()
            ->where(Order::COLUMN_NUMBER, $number)
            ->where(fn ($query) => $query
                ->where(Order::COLUMN_CUSTOMER_SNAPSHOT.'->mobile', $mobile)
                ->orWhere(Order::COLUMN_ADDRESS_SNAPSHOT.'->receiver_mobile', $mobile))
            ->with('statusLogs')
            ->first();
    }
}
