<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Actions;

use App\Modules\Cart\Data\CartLineData;
use App\Modules\Catalog\Actions\ReserveFlashDealUnits;
use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Checkout\Data\PanelOrderData;
use App\Modules\Checkout\Exceptions\CheckoutRefused;
use App\Modules\Checkout\Queries\CheckoutQuote;
use App\Modules\Checkout\Support\DiscountShares;
use App\Modules\Identity\Data\AddressSnapshotData;
use App\Modules\Identity\Queries\CustomerContact;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Data\CreatedOrderData;
use App\Modules\Orders\Data\NewOrderData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Events\OrderPaymentLinkRequested;
use Illuminate\Support\Facades\DB;

/**
 * «ثبت سفارش» in the panel (owner, 2026-11-19): staff place an order for an existing customer, to one of their saved
 * addresses, with SKUs at the site's prices (limits, availability and flash deals as on the site), shipping priced as
 * checkout prices it, and an optional discount with its reason, shared over the items. The order waits for payment as
 * long as a site order (15 to 30 minutes) and records which admin placed it; its payment link is texted to the customer.
 */
final readonly class PlaceOrderForCustomer
{
    public function __construct(
        private CheckoutQuote $quotes,
        private CustomerContact $contacts,
        private CreateOrder $createOrder,
        private ReserveFlashDealUnits $reserveFlashDealUnits,
    ) {}

    /**
     * @throws CheckoutRefused
     */
    public function __invoke(PanelOrderData $data): CreatedOrderData
    {
        $order = DB::transaction(function () use ($data): CreatedOrderData {
            $contact = $this->contacts->for($data->customerId) ?? throw CheckoutRefused::customerUnknown();
            $quantities = array_filter($data->quantities, fn (int $quantity): bool => $quantity > 0);

            if ($quantities === []) {
                throw CheckoutRefused::noItems();
            }

            $quote = $this->quotes->forItems($data->customerId, $data->addressId, $quantities);
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

            if ($data->manualDiscount < 0 || $data->manualDiscount > $quote->cart->subtotal) {
                throw CheckoutRefused::discountTooLarge($quote->cart->subtotal);
            }

            $reason = trim((string) $data->manualDiscountReason);
            $note = trim((string) $data->customerNote);

            $order = ($this->createOrder)(new NewOrderData(
                customerId: $data->customerId,
                address: $quote->address,
                customerSnapshot: $contact,
                subtotal: $quote->cart->subtotal,
                shippingPrice: $quote->shippingOption->price,
                shippingDiscount: $quote->shippingDiscount,
                shippingDiscountReason: $quote->shippingDiscount > 0 ? $quote->freeShippingReason : null,
                shippingTotal: $quote->shippingPrice,
                couponId: null,
                couponCode: null,
                couponSnapshot: null,
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
                customerNote: $note === '' ? null : $note,
                lines: $lines,
                discounts: DiscountShares::over($data->manualDiscount, $lines),
                source: OrderSource::Panel,
                createdByAdminId: $data->adminId,
                manualDiscount: $data->manualDiscount,
                manualDiscountReason: $data->manualDiscount > 0 && $reason !== '' ? $reason : null,
            ));

            foreach ($lines as $line) {
                /** @var CartLineData $line */
                if ($line->variant->flashDealId !== null) {
                    try {
                        ($this->reserveFlashDealUnits)($line->variant->flashDealId, $line->quantity, $line->variant->productName);
                    } catch (FlashDealRefused $refused) {
                        throw CheckoutRefused::dealEnded($refused->getMessage());
                    }
                }
            }

            OrderPaymentLinkRequested::dispatch($order->orderId);

            return $order;
        });

        return $order;
    }
}
