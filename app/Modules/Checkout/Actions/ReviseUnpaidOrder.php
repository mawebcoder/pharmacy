<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Actions;

use App\Modules\Cart\Actions\ReserveCoupon;
use App\Modules\Cart\Actions\SettleOrderCoupon;
use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartLineData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Catalog\Actions\ReleaseFlashDealUnits;
use App\Modules\Catalog\Actions\ReserveFlashDealUnits;
use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Checkout\Data\RevisedOrderData;
use App\Modules\Checkout\Data\ReviseOrderData;
use App\Modules\Checkout\Exceptions\CheckoutRefused;
use App\Modules\Checkout\Queries\CheckoutQuote;
use App\Modules\Checkout\Support\DiscountShares;
use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Identity\Queries\CustomerContact;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Data\NewOrderData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Events\OrderPaymentLinkRequested;
use App\Modules\Orders\Queries\EditableOrders;
use App\Modules\Payments\Actions\ClosePaymentLinks;
use Illuminate\Support\Facades\DB;

/**
 * Edits an unpaid order (owner, 2026-11-19): its customer on their order page (quantities, removing items, another saved
 * address), or staff for a panel order (also adding items and the discount). Everything is priced again as checkout
 * prices it; the coupon is checked again and kept while it still applies (else dropped, with the reason); flash deal
 * units and the coupon reservation follow the new items; open payment links are closed so the old amount cannot be
 * paid; the payment deadline stays. After a staff edit the new link is texted to the customer.
 */
final readonly class ReviseUnpaidOrder
{
    public function __construct(
        private EditableOrders $orders,
        private CheckoutQuote $quotes,
        private CustomerContact $contacts,
        private CreateOrder $createOrder,
        private SettleOrderCoupon $settleCoupon,
        private ReserveCoupon $reserveCoupon,
        private ReserveFlashDealUnits $reserveFlashDealUnits,
        private ReleaseFlashDealUnits $releaseFlashDealUnits,
        private ClosePaymentLinks $closePaymentLinks,
    ) {}

    /**
     * @throws CheckoutRefused
     */
    public function __invoke(ReviseOrderData $data): RevisedOrderData
    {
        return DB::transaction(function () use ($data): RevisedOrderData {
            $order = $this->orders->lockFor($data->orderId) ?? throw CheckoutRefused::notEditable();

            if (($data->customerId !== null && $order['customer_id'] !== $data->customerId)
                || ($data->customerId === null && $order['source'] !== OrderSource::Panel)) {
                throw CheckoutRefused::notEditable();
            }

            $quantities = array_filter($data->quantities, fn (int $quantity): bool => $quantity > 0);

            if ($quantities === []) {
                throw CheckoutRefused::noItems();
            }

            // Give back what the order held, so the new items are checked as if it held nothing.
            ($this->settleCoupon)($order['order_id'], paid: false);

            foreach ($order['items'] as $item) {
                if ($item['flash_deal_id'] !== null) {
                    ($this->releaseFlashDealUnits)($item['flash_deal_id'], $item['quantity']);
                }
            }

            $quote = $this->quotes->forItems($order['customer_id'], $data->addressId, $quantities, $order['coupon_code']);
            $lines = $quote->cart->lines;
            $refused = $quote->unavailableNames();

            foreach ($lines as $line) {
                if ($line->isAvailable() && $line->quantity > $line->variant->maxPerOrder) {
                    $refused[] = $line->variant->productName;
                }
            }

            if ($refused !== [] || count($lines) !== count($quantities)) {
                throw CheckoutRefused::unavailable($refused);
            }

            if (! $quote->address instanceof AddressSnapshotData) {
                throw CheckoutRefused::noAddress();
            }

            if (! $quote->address->isDeliveryEnabled) {
                throw CheckoutRefused::addressNotDeliverable();
            }

            $coupon = $quote->coupon;
            $couponDiscount = $coupon instanceof AppliedCouponData ? $coupon->discount : 0;
            $manual = $data->adminId !== null && $data->manualDiscount !== null ? $data->manualDiscount : $order['manual_discount'];
            $manualReason = $data->adminId !== null && $data->manualDiscount !== null ? trim((string) $data->manualDiscountReason) : (string) $order['manual_discount_reason'];

            if ($manual < 0 || $manual > $quote->cart->subtotal - $couponDiscount) {
                throw CheckoutRefused::discountTooLarge($quote->cart->subtotal - $couponDiscount);
            }

            $discounts = $coupon instanceof AppliedCouponData
                ? DiscountShares::over($couponDiscount, array_values(array_filter($lines, fn (CartLineData $line): bool => in_array($line->variant->variantId, $coupon->coveredVariantIds, true))))
                : [];

            foreach (DiscountShares::over($manual, $lines) as $variantId => $share) {
                $discounts[$variantId] = ($discounts[$variantId] ?? 0) + $share;
            }

            $this->createOrder->replaceContents($order['order_id'], new NewOrderData(
                customerId: $order['customer_id'],
                address: $quote->address,
                customerSnapshot: $this->contacts->for($order['customer_id']) ?? [],
                subtotal: $quote->cart->subtotal,
                shippingPrice: $quote->shippingOption->price,
                shippingDiscount: $quote->shippingDiscount,
                shippingDiscountReason: $quote->shippingDiscount > 0 ? $quote->freeShippingReason : null,
                shippingTotal: $quote->shippingPrice,
                couponId: $coupon?->couponId,
                couponCode: $coupon?->code,
                couponSnapshot: $coupon instanceof AppliedCouponData ? $this->quotes->couponSnapshot($coupon->couponId) : null,
                shippingMethod: $quote->shippingOption->method,
                shippingQuoteSnapshot: [
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
                ],
                customerNote: $order['customer_note'],
                lines: $lines,
                discounts: $discounts,
                source: $order['source'],
                manualDiscount: $manual,
                manualDiscountReason: $manual > 0 && $manualReason !== '' ? $manualReason : null,
            ));

            foreach ($lines as $line) {
                if ($line->variant->flashDealId !== null) {
                    try {
                        ($this->reserveFlashDealUnits)($line->variant->flashDealId, $line->quantity, $line->variant->productName);
                    } catch (FlashDealRefused $refused) {
                        throw CheckoutRefused::dealEnded($refused->getMessage());
                    }
                }
            }

            if ($coupon instanceof AppliedCouponData) {
                try {
                    $_ = ($this->reserveCoupon)($coupon->couponId, $order['customer_id'], $coupon->discount, $order['order_id']);
                } catch (CouponRefused $refused) {
                    throw CheckoutRefused::couponInvalid($refused->getMessage());
                }
            }

            ($this->closePaymentLinks)($order['order_id'], 'سفارش ویرایش شد');

            if ($data->adminId !== null) {
                OrderPaymentLinkRequested::dispatch($order['order_id']);
            }

            return new RevisedOrderData(
                total: $quote->cart->subtotal - array_sum($discounts) + $quote->shippingPrice,
                couponDropped: $order['coupon_code'] !== null && ! $coupon instanceof AppliedCouponData ? (string) $quote->couponProblem : null,
            );
        });
    }
}
