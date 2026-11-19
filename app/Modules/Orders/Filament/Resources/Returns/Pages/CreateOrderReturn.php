<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Returns\Pages;

use App\Modules\Orders\Actions\CreateOrderReturn as OpenOrderReturn;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use App\Modules\Orders\Filament\Resources\Returns\Schemas\OrderReturnForm;
use App\Modules\Orders\Models\Order;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * «ثبت مرجوعی» (S27): the order (filled in when opened from the order's page, `?order=`), the units per item and the
 * reason; saved as a draft with its refund and Tipax's price.
 */
final class CreateOrderReturn extends CreateRecord
{
    protected static string $resource = OrderReturnResource::class;

    protected static bool $canCreateAnother = false;

    protected function fillForm(): void
    {
        $orderId = request()->integer('order');

        $this->form->fill($orderId > 0 ? ['order_id' => $orderId, 'items' => OrderReturnForm::itemRows($orderId)] : []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $quantities = [];

        foreach ((array) ($data['items'] ?? []) as $row) {
            $quantities[(int) ($row['order_item_id'] ?? 0)] = (int) ($row['quantity'] ?? 0);
        }

        try {
            return app(OpenOrderReturn::class)(
                Order::query()->findOrFail((int) $data['order_id']),
                $quantities,
                isset($data['reason']) ? (string) $data['reason'] : null,
                auth('admin')->id() === null ? null : (int) auth('admin')->id(),
            );
        } catch (OrderRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();

            throw new Halt($refused->getMessage(), 0, $refused);
        }
    }

    protected function getRedirectUrl(): string
    {
        return OrderReturnResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'پیش‌نویس مرجوعی ثبت شد.';
    }
}
