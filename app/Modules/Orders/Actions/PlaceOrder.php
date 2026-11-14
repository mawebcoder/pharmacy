<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Cart\Actions\EmptyCart;
use App\Modules\Cart\Actions\ReserveCoupon;
use App\Modules\Cart\Data\AppliedCouponData;
use App\Modules\Cart\Data\CartLineData;
use App\Modules\Cart\Data\CheckoutQuoteData;
use App\Modules\Cart\Exceptions\CouponRefused;
use App\Modules\Cart\Queries\CheckoutQuote;
use App\Modules\Catalog\Queries\OrderItemSnapshots;
use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Identity\Queries\CustomerContact;
use App\Modules\Orders\Data\PlacedOrderData;
use App\Modules\Orders\Data\PlaceOrderData;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\CheckoutRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Support\OrderNumber;
use App\Modules\Payments\Actions\OpenPaymentLink;
use App\Modules\Payments\Data\PaymentRequestData;
use App\Modules\Payments\Exceptions\GatewayUnavailable;
use App\Modules\Pricing\Data\PriceRecordData;
use App\Modules\Pricing\Queries\CurrentPriceRecords;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Creates the order when the customer presses «پرداخت», right before the gateway (roadmap D13.4, owner 2026-11-14).
 * Everything is checked again from the cart now: every item can still be bought within its limit, the coupon is still
 * valid, the address is theirs and its city has delivery, and the total equals what the page showed (else nothing is
 * created and the page reloads with the reason). Then, in one transaction: the order with copies of the address,
 * customer, coupon and shipping quote; each item with its names, the price record of this moment and its share of
 * the coupon discount (the order's discount_total is the sum of its items' discounts); the first status log; the
 * coupon reserved; the cart emptied. The payment link is opened after the commit; when the gateway cannot be reached
 * the order stays payable from «سفارش‌ها» until it expires.
 */
final readonly class PlaceOrder
{
    public function __construct(
        private CheckoutQuote $quotes,
        private CurrentPriceRecords $priceRecords,
        private OrderItemSnapshots $itemSnapshots,
        private CustomerContact $contacts,
        private ReserveCoupon $reserveCoupon,
        private EmptyCart $emptyCart,
        private OpenPaymentLink $openPaymentLink,
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
            $order = DB::transaction(fn (): Order => $this->place($data));
        } finally {
            $lock->release();
        }

        try {
            $paymentUrl = ($this->openPaymentLink)(new PaymentRequestData(
                orderId: $order->getId(),
                amount: $order->getTotal(),
                description: 'سفارش '.$order->getNumber(),
                mobile: (string) ($order->getCustomerSnapshot()['mobile'] ?? ''),
                expiresAt: $order->getExpiresAt() ?? now(),
            ));
        } catch (GatewayUnavailable) {
            $paymentUrl = null;
        }

        return new PlacedOrderData($order->getId(), (string) $order->getNumber(), $paymentUrl);
    }

    private function place(PlaceOrderData $data): Order
    {
        $quote = $this->quotes->forCustomer($data->customerId, $data->couponCode) ?? throw CheckoutRefused::cartEmpty();
        $address = $this->check($quote, $data);
        $lines = $quote->cart->lines;
        $variantIds = array_map(fn (CartLineData $line): int => $line->variant->variantId, $lines);
        $prices = $this->priceRecords->for($variantIds);
        $snapshots = $this->itemSnapshots->for($variantIds);
        $discounts = $this->shareDiscount($quote->coupon, $lines);
        $now = now();

        $order = (new Order)
            ->setCustomerId($data->customerId)
            ->setStatus(OrderStatus::Pending)
            ->setPaymentStatus(PaymentStatus::Pending)
            ->setAddressId($address->addressId)
            ->setAddressSnapshot($address->toArray())
            ->setCustomerSnapshot($this->contacts->for($data->customerId) ?? [])
            ->setSubtotal($quote->cart->subtotal)
            ->setDiscountTotal(array_sum($discounts))
            ->setShippingTotal($quote->shippingPrice)
            ->setTotal($quote->cart->subtotal - array_sum($discounts) + $quote->shippingPrice)
            ->setCouponId($quote->coupon?->couponId)
            ->setCouponCode($quote->coupon?->code)
            ->setCouponSnapshot($quote->coupon instanceof AppliedCouponData ? $this->quotes->couponSnapshot($quote->coupon->couponId) : null)
            ->setShippingMethod('flat')
            ->setShippingQuoteSnapshot([
                'method' => 'flat',
                'label' => 'ارسال',
                'price' => $quote->shippingPrice,
                'free' => $quote->freeShipping,
                'free_reason' => $quote->freeShippingReason,
                'flat_rate' => $quote->flatRate,
                'free_shipping_threshold' => $quote->freeShippingThreshold,
            ])
            ->setCustomerNote($data->customerNote)
            ->setPlacedAt($now)
            ->setExpiresAt($now->copy()->addMinutes((int) config('payments.pending_expiry_minutes')));
        $order->save();
        $order->setNumber(OrderNumber::for($order->getId(), $now))->save();

        ChangeOrderStatus::log($order->getId(), null, OrderStatus::Pending, OrderActor::Customer, $data->customerId);

        foreach ($lines as $line) {
            $this->addItem($order, $line, $prices[$line->variant->variantId] ?? null, $snapshots[$line->variant->variantId] ?? [], $discounts[$line->variant->variantId] ?? 0);
        }

        if ($quote->coupon instanceof AppliedCouponData) {
            try {
                $reservation = ($this->reserveCoupon)($quote->coupon->couponId, $data->customerId, $quote->coupon->discount, $order->getId());
                $order->setCouponId($reservation->getCouponId());
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
     * The coupon discount shared over the lines it covers in proportion to their totals (whole rial; the remainder goes
     * to the last covered line), so each item knows what it really sold for and the shares add up to the discount.
     *
     * @param  list<CartLineData>  $lines
     * @return array<int, int> variant id => discount
     */
    private function shareDiscount(?AppliedCouponData $coupon, array $lines): array
    {
        if (! $coupon instanceof AppliedCouponData || $coupon->discount === 0) {
            return [];
        }

        $covered = array_values(array_filter($lines, fn (CartLineData $line): bool => in_array($line->variant->variantId, $coupon->coveredVariantIds, true)));
        $coveredTotal = array_sum(array_map(fn (CartLineData $line): int => $line->lineTotal, $covered));
        $shares = [];
        $given = 0;

        foreach ($covered as $index => $line) {
            $share = $index === count($covered) - 1
                ? $coupon->discount - $given
                : intdiv($coupon->discount * $line->lineTotal, max(1, $coveredTotal));
            $shares[$line->variant->variantId] = $share;
            $given += $share;
        }

        return $shares;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function addItem(Order $order, CartLineData $line, ?PriceRecordData $price, array $snapshot, int $discount): void
    {
        $variant = $line->variant;

        $item = (new OrderItem)
            ->setOrderId($order->getId())
            ->setVariantId($variant->variantId)
            ->setProductId($variant->productId)
            ->setVariantPriceId($price?->variantPriceId)
            ->setProductName($variant->productName)
            ->setVariantTitle($variant->variantTitle)
            ->setSku($variant->sku)
            ->setBrandName($variant->brandName)
            ->setImageUrl($variant->image?->url(320, 'webp'))
            ->setProductType($variant->productType)
            ->setRequiresPrescription($variant->requiresPrescription)
            ->setQuantity($line->quantity)
            ->setProductSnapshot($snapshot)
            ->setProviderId($price?->providerId)
            ->setProviderName($price?->providerName)
            ->setUnitCost($price?->providerCost)
            ->setCommissionPercent($price?->commissionPercent)
            ->setCommissionSource($price?->commissionSource)
            ->setProviderChoice($price?->providerChoice)
            ->setOfficialPrice($price?->officialPrice)
            ->setListPrice($price->listPrice ?? $variant->price)
            ->setSalePrice($price?->salePrice)
            ->setUnitPrice($variant->price)
            ->setDiscount($discount)
            ->setTotal($line->lineTotal - $discount);
        $item->save();
    }
}
