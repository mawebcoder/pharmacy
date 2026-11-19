<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Models\Order;

/**
 * What a message about an order needs (roadmap S37): its number, the customer's name and mobile as they were at
 * checkout (the name «مشتری» when they gave none), and the carrier's tracking code once it is on its way.
 */
final readonly class OrderNotice
{
    /**
     * @return array{number: string, name: string, mobile: string, tracking_code: string|null}|null null when the order or its mobile is missing
     */
    public function for(int $orderId): ?array
    {
        $order = Order::query()->find($orderId);
        $mobile = $order instanceof Order ? (string) ($order->getCustomerSnapshot()['mobile'] ?? '') : '';

        if (! $order instanceof Order || $mobile === '' || $order->getNumber() === null) {
            return null;
        }

        $name = trim((string) ($order->getCustomerSnapshot()['name'] ?? ''));

        return [
            'number' => $order->getNumber(),
            'name' => $name !== '' ? $name : 'مشتری',
            'mobile' => $mobile,
            'tracking_code' => $order->getTrackingCode(),
        ];
    }
}
