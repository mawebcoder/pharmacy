<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Cart\Data\CartLineData;
use App\Modules\Catalog\Queries\OrderItemSnapshots;
use App\Modules\Orders\Data\CreatedOrderData;
use App\Modules\Orders\Data\NewOrderData;
use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Support\OrderNumber;
use App\Modules\Pricing\Data\PriceRecordData;
use App\Modules\Pricing\Enums\ProviderChoice;
use App\Modules\Pricing\Queries\CurrentPriceRecords;
use App\Modules\Pricing\Queries\SupplierOptions;

/**
 * Saves an order the Checkout module has checked (roadmap D13.4; split from PlaceOrder 2026-11-18): the order, pending,
 * with copies of the address, customer, coupon and shipping quote; each item with its names, the price record of this
 * moment and its share of the coupon discount (the order's discount_total is the sum of its items' discounts); its
 * number; and the first status log. The caller runs it inside its transaction.
 */
final readonly class CreateOrder
{
    public function __construct(
        private CurrentPriceRecords $priceRecords,
        private OrderItemSnapshots $itemSnapshots,
        private SupplierOptions $suppliers,
    ) {}

    public function __invoke(NewOrderData $data): CreatedOrderData
    {
        $variantIds = array_map(fn (CartLineData $line): int => $line->variant->variantId, $data->lines);
        $prices = $this->priceRecords->for($variantIds);
        $snapshots = $this->itemSnapshots->for($variantIds);
        $now = now();

        $order = (new Order)
            ->setCustomerId($data->customerId)
            ->setStatus(OrderStatus::Pending)
            ->setPaymentStatus(PaymentStatus::Pending)
            ->setAddressId($data->address->addressId)
            ->setAddressSnapshot($data->address->toArray())
            ->setCustomerSnapshot($data->customerSnapshot)
            ->setSubtotal($data->subtotal)
            ->setDiscountTotal($data->discountTotal())
            ->setShippingPrice($data->shippingPrice)
            ->setShippingDiscount($data->shippingDiscount)
            ->setShippingDiscountReason($data->shippingDiscountReason)
            ->setShippingTotal($data->shippingTotal)
            ->setTotal($data->total())
            ->setCouponId($data->couponId)
            ->setCouponCode($data->couponCode)
            ->setCouponSnapshot($data->couponSnapshot)
            ->setShippingMethod($data->shippingMethod)
            ->setShippingQuoteSnapshot($data->shippingQuoteSnapshot)
            ->setCustomerNote($data->customerNote)
            ->setPlacedAt($now)
            ->setExpiresAt($now->copy()->addMinutes((int) config('payments.pending_expiry_minutes')));
        $order->save();
        $order->setNumber(OrderNumber::for($order->getId(), $now))->save();

        ChangeOrderStatus::log($order->getId(), null, OrderStatus::Pending, OrderActor::Customer, $data->customerId);

        foreach ($data->lines as $line) {
            $variantId = $line->variant->variantId;
            $this->addItem($order, $line, $prices[$variantId] ?? null, $snapshots[$variantId] ?? [], $data->discounts[$variantId] ?? 0);
        }

        return new CreatedOrderData($order->getId(), (string) $order->getNumber(), $order->getTotal(), $order->getExpiresAt() ?? $now);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function addItem(Order $order, CartLineData $line, ?PriceRecordData $price, array $snapshot, int $discount): void
    {
        $variant = $line->variant;
        $listPrice = $price->listPrice ?? $variant->price;
        $harajUnit = max(0, $listPrice - $variant->price);
        $paidTotal = $line->lineTotal - $discount;
        $paidUnit = intdiv($paidTotal, max(1, $line->quantity));
        // Bought under a flash deal from a chosen supplier (S28, owner 2026-11-17): that supplier and its cost.
        $dealProviderId = $variant->flashDealProviderId;
        $providerId = $dealProviderId ?? $price?->providerId;
        $providerName = $dealProviderId === null ? $price?->providerName : $this->suppliers->name($dealProviderId);
        $unitCost = $dealProviderId === null ? $price?->providerCost : $this->suppliers->cost($dealProviderId, $variant->variantId);

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
            ->setFlashDealId($variant->flashDealId)
            ->setQuantity($line->quantity)
            ->setProductSnapshot($snapshot)
            ->setProviderId($providerId)
            ->setProviderName($providerName)
            ->setUnitCost($unitCost)
            ->setCommissionPercent($price?->commissionPercent)
            ->setCommissionSource($price?->commissionSource)
            ->setProviderChoice($dealProviderId === null ? $price?->providerChoice : ProviderChoice::FlashDeal->value)
            ->setOfficialPrice($price?->officialPrice)
            ->setListPrice($listPrice)
            ->setSalePrice($price?->salePrice)
            ->setUnitPrice($variant->price)
            ->setHarajDiscountUnit($harajUnit)
            ->setHarajDiscountTotal($harajUnit * $line->quantity)
            ->setCouponDiscountUnit($variant->price - $paidUnit)
            ->setCouponDiscountTotal($discount)
            ->setPaidUnit($paidUnit)
            ->setPaidTotal($paidTotal);
        $item->save();
    }
}
