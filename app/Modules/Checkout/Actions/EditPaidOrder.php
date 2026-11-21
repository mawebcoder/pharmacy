<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Actions;

use App\Modules\Cart\Data\CartLineData;
use App\Modules\Catalog\Actions\ReleaseFlashDealUnits;
use App\Modules\Checkout\Data\PaidOrderEditData;
use App\Modules\Checkout\Data\PaidOrderEditedData;
use App\Modules\Checkout\Exceptions\CheckoutRefused;
use App\Modules\Checkout\Queries\CheckoutQuote;
use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Events\OrderPaymentLinkRequested;
use App\Modules\Orders\Queries\PaidEditableOrders;
use App\Modules\Payments\Actions\ClosePaymentLinks;
use App\Modules\Payments\Actions\CreateRefund;
use App\Modules\Payments\Enums\RefundSource;
use App\Modules\Payments\Exceptions\RefundRefused;
use App\Modules\Shipping\Actions\PackOrder;
use Illuminate\Support\Facades\DB;

/**
 * Staff edit a paid order before it goes out (owner, 2026-11-19): items and units, and another saved address. What the
 * customer already paid stays theirs: kept units cost what they paid per unit, new SKUs the site's price now, and
 * shipping is priced again. When the new total is higher, the customer owes the difference: a payment link open for an
 * hour is texted and the «پرداخت» button shows on their order; the order does not go out until it is paid. When it is
 * lower, a refund record opens in «بازگشت وجه‌ها», waiting for approval. Flash deal units of removed or lowered items
 * are given back (a flash deal item cannot be raised), and the order is packed again.
 */
final readonly class EditPaidOrder
{
    public const int DIFFERENCE_LINK_MINUTES = 60;

    public function __construct(
        private PaidEditableOrders $orders,
        private CheckoutQuote $quotes,
        private CreateOrder $createOrder,
        private ReleaseFlashDealUnits $releaseFlashDealUnits,
        private ClosePaymentLinks $closePaymentLinks,
        private CreateRefund $createRefund,
        private PackOrder $packOrder,
    ) {}

    /**
     * @throws CheckoutRefused
     */
    public function __invoke(PaidOrderEditData $data): PaidOrderEditedData
    {
        $result = DB::transaction(function () use ($data): PaidOrderEditedData {
            $order = $this->orders->lockFor($data->orderId) ?? throw CheckoutRefused::paidNotEditable();
            $quantities = array_filter($data->quantities, fn (int $quantity): bool => $quantity > 0);

            if ($quantities === []) {
                throw CheckoutRefused::noItems();
            }

            $existing = [];

            foreach ($order['items'] as $item) {
                $existing[$item['variant_id']] = $item;
                $new = $quantities[$item['variant_id']] ?? 0;

                if ($item['flash_deal_id'] !== null && $new > $item['quantity']) {
                    throw CheckoutRefused::dealEnded('تعداد کالای پیشنهاد لحظه‌ای را نمی‌توان پس از پرداخت افزایش داد.');
                }
            }

            // The whole new order for shipping and the address; SKUs new to the order are checked and priced from it.
            $quote = $this->quotes->forItems($order['customer_id'], $data->addressId, $quantities);

            if (! $quote->address instanceof AddressSnapshotData) {
                throw CheckoutRefused::noAddress();
            }

            if (! $quote->address->isDeliveryEnabled) {
                throw CheckoutRefused::addressNotDeliverable();
            }

            $newLines = array_values(array_filter($quote->cart->lines, fn (CartLineData $line): bool => ! isset($existing[$line->variant->variantId])));
            $refused = [];

            foreach ($newLines as $line) {
                if (! $line->isAvailable() || $line->quantity > $line->variant->maxPerOrder) {
                    $refused[] = $line->variant->productName;
                }
            }

            $newVariantIds = array_diff(array_keys($quantities), array_keys($existing));

            if ($refused !== [] || count($newLines) !== count($newVariantIds)) {
                throw CheckoutRefused::unavailable($refused);
            }

            $kept = [];
            $itemsTotal = 0;

            foreach ($order['items'] as $item) {
                $new = $quantities[$item['variant_id']] ?? 0;
                $kept[$item['item_id']] = $new;
                $itemsTotal += $new === $item['quantity'] ? $item['paid_total'] : $item['paid_unit'] * $new;

                if ($item['flash_deal_id'] !== null && $new < $item['quantity']) {
                    ($this->releaseFlashDealUnits)($item['flash_deal_id'], $item['quantity'] - $new);
                }
            }

            foreach ($newLines as $line) {
                $itemsTotal += $line->lineTotal;
            }

            $total = $itemsTotal + $quote->shippingPrice;
            $owed = $order['difference_due'] + $total - $order['total'];
            $customerOwes = max(0, $owed);

            $this->createOrder->editPaid(
                $order['order_id'],
                $kept,
                $newLines,
                $quote->address,
                $quote->shippingPrice,
                [
                    'method' => $quote->shippingOption->method,
                    'option' => $quote->shippingOption->key,
                    'label' => $quote->shippingOption->title,
                    'option_price' => $quote->shippingOption->price,
                    'price' => $quote->shippingPrice,
                    'delivery_days' => $quote->shippingOption->deliveryDays,
                    'is_estimate' => $quote->shippingOption->isEstimate,
                    'details' => $quote->shippingOption->details,
                    'free' => $quote->freeShipping,
                    'free_reason' => $quote->freeShippingReason,
                    'free_shipping_threshold' => $quote->freeShippingThreshold,
                    'edited' => true,
                ],
                $customerOwes,
                $customerOwes > 0 ? now()->addMinutes(self::DIFFERENCE_LINK_MINUTES) : null,
            );

            // An earlier difference link is for another amount.
            ($this->closePaymentLinks)($order['order_id'], 'سفارش دوباره ویرایش شد');
            $refund = 0;

            if ($owed < 0) {
                try {
                    ($this->createRefund)($order['order_id'], -$owed, RefundSource::OrderEdit, 'ویرایش سفارش '.$order['number'].' (کاهش مبلغ)', adminId: $data->adminId);
                    $refund = -$owed;
                } catch (RefundRefused $refused) {
                    throw CheckoutRefused::refundRefused($refused->getMessage());
                }
            }

            if ($customerOwes > 0) {
                OrderPaymentLinkRequested::dispatch($order['order_id']);
            }

            return new PaidOrderEditedData($total, $customerOwes, $refund);
        });

        ($this->packOrder)($data->orderId);

        return $result;
    }
}
