<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Pages;

use App\Modules\Checkout\Actions\PlaceOrderForCustomer;
use App\Modules\Checkout\Data\PanelOrderData;
use App\Modules\Checkout\Exceptions\CheckoutRefused;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Models\Order;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * «ثبت سفارش» (owner, 2026-11-19): places the order for the customer through Checkout, so prices, limits, flash deals
 * and shipping are the site's; then opens the order, where its payment link can be copied or texted again.
 */
final class CreatePanelOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'ثبت سفارش';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $quantities = [];

        foreach ((array) ($data['items'] ?? []) as $row) {
            $variantId = (int) ($row['variant_id'] ?? 0);
            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + (int) ($row['quantity'] ?? 0);
        }

        try {
            $placed = app(PlaceOrderForCustomer::class)(new PanelOrderData(
                customerId: (int) $data['customer_id'],
                addressId: (int) $data['address_id'],
                quantities: $quantities,
                manualDiscount: (int) ($data['manual_discount'] ?? 0),
                manualDiscountReason: isset($data['manual_discount_reason']) ? (string) $data['manual_discount_reason'] : null,
                customerNote: isset($data['customer_note']) ? (string) $data['customer_note'] : null,
                adminId: (int) auth('admin')->id(),
            ));
        } catch (CheckoutRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->persistent()->send();

            throw new Halt($refused->getMessage(), 0, $refused);
        }

        return Order::query()->findOrFail($placed->orderId);
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'سفارش ثبت شد؛ لینک پرداخت برای مشتری پیامک شد.';
    }
}
