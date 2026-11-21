<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Actions;

use App\Modules\Cart\Actions\EmptyCart;
use App\Modules\Cart\Actions\ReserveCoupon;
use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartLineData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Catalog\Actions\ReserveFlashDealUnits;
use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Checkout\Data\CheckoutQuoteData;
use App\Modules\Checkout\Data\PlacedOrderData;
use App\Modules\Checkout\Data\PlaceOrderData;
use App\Modules\Checkout\Exceptions\CheckoutRefused;
use App\Modules\Checkout\Queries\CheckoutQuote;
use App\Modules\Checkout\Support\DiscountShares;
use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Identity\Queries\CustomerContact;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Data\CreatedOrderData;
use App\Modules\Orders\Data\NewOrderData;
use App\Modules\Payments\Actions\OpenPaymentLink;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Exceptions\GatewayUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Creates the order when the customer presses «پرداخت», right before the gateway (roadmap D13.4, owner 2026-11-14).
 * Everything is checked again from the cart now: every item can still be bought within its limit, the coupon is still
 * valid, the address is theirs and its city has delivery, and the total equals what the page showed (else nothing is
 * created and the page reloads with the reason). Then, in one transaction: the order saved by the Orders module
 * (CreateOrder) with its items, each with its share of the coupon discount; flash deal units counted; the coupon
 * reserved; the cart emptied. The payment link is opened after the commit; when the gateway cannot be reached
 * the order stays payable from «سفارش‌ها» until it expires.
 */
final readonly class PlaceOrder
{
    public function __construct(
        private CheckoutQuote $quotes,
        private CustomerContact $contacts,
        private CreateOrder $createOrder,
        private ReserveCoupon $reserveCoupon,
        private EmptyCart $emptyCart,
        private OpenPaymentLink $openPaymentLink,
        private ReserveFlashDealUnits $reserveFlashDealUnits,
    ) {}

    /**
     * @throws CheckoutRefused
     */
    #[\NoDiscard]
    public function __invoke(PlaceOrderData $data): PlacedOrderData
    {
        $lock = Cache::lock('checkout:'.$data->customerId, 20);

        if (! $lock->get()) {
            throw CheckoutRefused::busy();
        }

        try {
            $order = DB::transaction(fn (): CreatedOrderData => $this->place($data));
        } finally {
            $lock->release();
        }

        try {
            $paymentUrl = ($this->openPaymentLink)(new PaymentRequestData(
                orderId: $order->orderId,
                amount: $order->total,
                description: 'سفارش '.$order->number,
                mobile: (string) ($this->contacts->for($data->customerId)['mobile'] ?? ''),
                expiresAt: $order->expiresAt,
            ));
        } catch (GatewayUnavailable) {
            $paymentUrl = null;
        }

        return new PlacedOrderData($order->orderId, $order->number, $paymentUrl);
    }

    private function place(PlaceOrderData $data): CreatedOrderData
    {
        $quote = $this->quotes->forCustomer($data->customerId, $data->couponCode) ?? throw CheckoutRefused::cartEmpty();
        $address = $this->check($quote, $data);
        $lines = $quote->cart->lines;

        $order = ($this->createOrder)(new NewOrderData(
            customerId: $data->customerId,
            address: $address,
            customerSnapshot: $this->contacts->for($data->customerId) ?? [],
            subtotal: $quote->cart->subtotal,
            shippingPrice: $quote->shippingOption->price,
            shippingDiscount: $quote->shippingDiscount,
            shippingDiscountReason: $quote->shippingDiscount > 0 ? $quote->freeShippingReason : null,
            shippingTotal: $quote->shippingPrice,
            couponId: $quote->coupon?->couponId,
            couponCode: $quote->coupon?->code,
            couponSnapshot: $quote->coupon instanceof AppliedCouponData ? $this->quotes->couponSnapshot($quote->coupon->couponId) : null,
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
            customerNote: $data->customerNote,
            lines: $lines,
            discounts: $this->shareDiscount($quote->coupon, $lines),
        ));

        // Flash deal units are counted now, locked, so the last units are never sold twice (owner, 2026-11-16).
        foreach ($lines as $line) {
            if ($line->variant->flashDealId !== null) {
                try {
                    ($this->reserveFlashDealUnits)($line->variant->flashDealId, $line->quantity, $line->variant->productName);
                } catch (FlashDealRefused $refused) {
                    throw CheckoutRefused::dealEnded($refused->getMessage());
                }
            }
        }

        if ($quote->coupon instanceof AppliedCouponData) {
            try {
                $_ = ($this->reserveCoupon)($quote->coupon->couponId, $data->customerId, $quote->coupon->discount, $order->orderId);
            } catch (CouponRefused $refused) {
                throw CheckoutRefused::couponInvalid($refused->getMessage());
            }
        }

        ($this->emptyCart)($quote->cartId);

        return $order;
    }

    /**
     * The re-checks of D13.4; returns the address the order goes to.
     *
     * @throws CheckoutRefused
     */
    private function check(CheckoutQuoteData $quote, PlaceOrderData $data): AddressSnapshotData
    {
        if ($quote->cart->isEmpty()) {
            throw CheckoutRefused::cartEmpty();
        }

        $refused = $quote->unavailableNames();

        foreach ($quote->cart->lines as $line) {
            if ($line->isAvailable() && $line->quantity > $line->variant->maxPerOrder) {
                $refused[] = $line->variant->productName;
            }
        }

        if ($refused !== []) {
            throw CheckoutRefused::unavailable($refused);
        }

        if ($data->couponCode !== null && $data->couponCode !== '' && ! $quote->coupon instanceof AppliedCouponData) {
            throw CheckoutRefused::couponInvalid((string) $quote->couponProblem);
        }

        if (! $quote->address instanceof AddressSnapshotData) {
            throw CheckoutRefused::noAddress();
        }

        if (! $quote->address->isDeliveryEnabled) {
            throw CheckoutRefused::addressNotDeliverable();
        }

        if ($quote->total() !== $data->shownTotal) {
            throw CheckoutRefused::totalChanged($data->shownTotal, $quote->total());
        }

        return $quote->address;
    }

    /**
     * The coupon discount shared over the lines it covers (DiscountShares).
     *
     * @param  list<CartLineData>  $lines
     * @return array<int, int> variant id => discount
     */
    private function shareDiscount(?AppliedCouponData $coupon, array $lines): array
    {
        if (! $coupon instanceof AppliedCouponData) {
            return [];
        }

        return DiscountShares::over($coupon->discount, array_values(array_filter($lines, fn (CartLineData $line): bool => in_array($line->variant->variantId, $coupon->coveredVariantIds, true))));
    }
}
