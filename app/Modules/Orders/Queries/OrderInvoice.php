<?php

declare(strict_types=1);

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Queries\OrderPayments;
use App\Modules\Settings\Queries\StoreSettings;

/**
 * An order's sales invoice («فاکتور فروش», owner 2026-11-19) as a printable page, A4, right to left: the store, the
 * customer and address, every item with its price, discount and total, the totals, the payment reference and the
 * carrier's tracking code with its barcode. Read only from the order's own copies. «چاپ فاکتور» opens it in the
 * browser's print dialog (save as PDF from there); it goes with the parcel and to the customer.
 */
final readonly class OrderInvoice
{
    public function __construct(
        private StoreSettings $settings,
        private OrderPayments $payments,
    ) {}

    public static function isAvailable(Order $order): bool
    {
        return $order->getPaidAt() !== null;
    }

    /**
     * The customer's own invoice for their order page, or null when the order is not theirs or not paid yet.
     */
    public function forCustomer(int $customerId, int $orderId): ?string
    {
        $order = Order::query()->ownedBy($customerId)->find($orderId);

        return $order instanceof Order && self::isAvailable($order) ? $this->html($order) : null;
    }

    public function html(Order $order): string
    {
        return view('orders::invoice-print', [
            'order' => $order->loadMissing('items'),
            'storeName' => (string) config('app.display_name'),
            'storeAddress' => $this->settings->address() ?? $this->settings->shippingSender()['address'],
            'storePhone' => $this->settings->phone(),
            'storePostalCode' => $this->settings->shippingSender()['postal_code'],
            'reference' => $this->payments->paidReference($order->getId()),
        ])->render();
    }
}
