<?php

declare(strict_types=1);

namespace App\Modules\Profile\Livewire;

use App\Modules\Checkout\Actions\ReviseUnpaidOrder;
use App\Modules\Checkout\Data\ReviseOrderData;
use App\Modules\Checkout\Exceptions\CheckoutRefused;
use App\Modules\Identity\Queries\CustomerAddresses;
use App\Modules\Orders\Actions\CancelOrderByCustomer;
use App\Modules\Orders\Actions\RequestOrderReturn;
use App\Modules\Orders\Data\ReturnRowData;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\ReturnReason;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Livewire\Concerns\HandlesOrderActions;
use App\Modules\Orders\Queries\OrderReturns;
use App\Modules\Orders\Support\OrderCheckpoints;
use App\Modules\Payments\Queries\OrderPayments;
use App\Modules\Shared\Support\PersianDigits;
use App\Modules\Shared\Support\Toman;
use App\Modules\Shipping\Exceptions\ParcelCheckTooSoon;
use App\Modules\Shipping\Exceptions\TipaxRequestFailed;
use App\Modules\Shipping\Queries\ParcelTrackingStatus;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * One order in «سفارش‌ها» (owner, 2026-11-14; the full tracking page stays in S24): number, date, status with its
 * checkpoint tracker (OrderCheckpoints, owner 2026-11-16), items, address, totals and payment, with «پرداخت», «لغو سفارش» or «سفارش دوباره». Everything is read from
 * the order's own copies (roadmap D3.4 «Order pages read only these copies»), and only the owner can open it.
 */
final class OrderDetail extends Component
{
    use HandlesOrderActions;
    use Interactions;

    #[Locked]
    public int $orderId;

    /** What the last «استعلام از سرویس ارسال» said (S37). */
    public ?string $parcelNotice = null;

    public function mount(int $order): void
    {
        $this->orderId = $this->ownOrder($order)->getId();
    }

    /**
     * «لغو سفارش» is offered only here, not in the order list (owner, 2026-11-17).
     */
    public function cancel(int $orderId, CancelOrderByCustomer $cancelOrder): void
    {
        try {
            $cancelOrder($this->ownOrder($orderId), $this->customerId());
            $this->toast()->success('سفارش لغو شد؛ مبلغ پرداختی به حساب شما بازگردانده می‌شود.')->send();
        } catch (OrderRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();
        }
    }

    /**
     * «ویرایش سفارش» of an unpaid order (owner, 2026-11-19): units per SKU (0 removes it) and the address.
     *
     * @var array<int, int|string>
     */
    public array $editQuantities = [];

    public ?int $editAddressId = null;

    public bool $editing = false;

    public function startEdit(): void
    {
        $order = $this->ownOrder($this->orderId);

        if (! $order->canBePaid()) {
            return;
        }

        $this->editQuantities = [];

        foreach ($order->items as $item) {
            $this->editQuantities[(int) $item->getVariantId()] = $item->getQuantity();
        }

        $this->editAddressId = $order->getAddressId();
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
    }

    /**
     * Saves the edit: priced again; the customer pays the new total with «پرداخت».
     */
    public function saveEdit(ReviseUnpaidOrder $revise): void
    {
        $this->validate([
            'editQuantities.*' => ['required', 'integer', 'min:0', 'max:100'],
            'editAddressId' => ['required', 'integer'],
        ]);

        try {
            $revised = $revise(new ReviseOrderData(
                orderId: $this->orderId,
                addressId: (int) $this->editAddressId,
                quantities: array_map(intval(...), $this->editQuantities),
                customerId: $this->customerId(),
            ));
        } catch (CheckoutRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();

            return;
        }

        $this->editing = false;
        $this->toast()->success('سفارش ویرایش شد؛ مبلغ تازه '.Toman::format($revised->total).'. برای تکمیل، «پرداخت» را بزنید.'
            .($revised->couponDropped === null ? '' : ' کد تخفیف دیگر روی این سفارش اعمال نمی‌شود: '.$revised->couponDropped))->send();
    }

    /**
     * The rows of «درخواست مرجوعی» being filled in: order_item_id, quantity, reason, note.
     *
     * @var list<array{order_item_id: int|string|null, quantity: int|string|null, reason: string|null, note: string|null}>
     */
    public array $returnRows = [];

    public string $returnNote = '';

    /**
     * «درخواست مرجوعی» (S27, owner 2026-11-19): opens the form with one row, within 24 hours of delivery.
     */
    public function startReturn(): void
    {
        $this->resetErrorBag();
        $this->returnRows = [self::emptyReturnRow()];
    }

    public function addReturnRow(): void
    {
        $this->returnRows[] = self::emptyReturnRow();
    }

    public function removeReturnRow(int $index): void
    {
        $this->returnRows = array_values(array_filter($this->returnRows, fn (int $key): bool => $key !== $index, ARRAY_FILTER_USE_KEY));
    }

    /**
     * Sends the return request: it reaches staff as a draft, and they are texted.
     */
    public function requestReturn(RequestOrderReturn $request): void
    {
        $this->validate([
            'returnRows' => ['required', 'array', 'min:1', 'max:20'],
            'returnRows.*.order_item_id' => ['required', 'integer'],
            'returnRows.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'returnRows.*.reason' => ['required', 'string', 'in:'.implode(',', array_keys(ReturnReason::customerOptions()))],
            'returnRows.*.note' => ['nullable', 'string', 'max:500'],
            'returnNote' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'returnRows.*.order_item_id' => 'کالا',
            'returnRows.*.quantity' => 'تعداد',
            'returnRows.*.reason' => 'دلیل',
            'returnRows.*.note' => 'توضیح',
            'returnNote' => 'توضیح',
        ]);

        try {
            $return = $request(
                $this->ownOrder($this->orderId),
                $this->customerId(),
                array_map(fn (array $row): ReturnRowData => ReturnRowData::fromArray($row), $this->returnRows),
                strip_tags($this->returnNote),
            );
        } catch (OrderRefused $refused) {
            $this->toast()->warning($refused->getMessage())->send();

            return;
        }

        $this->returnRows = [];
        $this->returnNote = '';
        $this->toast()->success('درخواست مرجوعی '.$return->getNumber().' ثبت شد؛ پس از بررسی با شما تماس می‌گیریم.')->send();
    }

    /**
     * «استعلام از سرویس ارسال» (owner, 2026-11-19): asks the carrier now, an hour after its last successful answer at
     * the soonest; the answer shows in the parcel box.
     */
    public function inquireParcel(ParcelTrackingStatus $parcels): void
    {
        $order = $this->ownOrder($this->orderId);

        if ($order->getStatus() !== OrderStatus::OnTheWay || blank($order->getTrackingCode())) {
            return;
        }

        try {
            $parcels->checkIfDue($order->getId(), (string) $order->getTrackingCode());
            $this->parcelNotice = 'آخرین وضعیت مرسوله از سرویس ارسال دریافت شد.';
        } catch (ParcelCheckTooSoon $tooSoon) {
            $this->parcelNotice = 'وضعیت مرسوله کمتر از یک ساعت پیش به‌روز شده است؛ '.PersianDigits::toPersian((string) $tooSoon->minutesLeft).' دقیقه دیگر دوباره امتحان کنید.';
        } catch (TipaxRequestFailed) {
            $this->parcelNotice = 'سرویس ارسال پاسخ نداد؛ کمی بعد دوباره امتحان کنید.';
        }
    }

    public function render(OrderPayments $payments, ParcelTrackingStatus $parcels, OrderReturns $returns, CustomerAddresses $addresses): View
    {
        $order = $this->ownOrder($this->orderId)->load(['items', 'statusLogs']);
        // Tipax's parcel status while the order is on its way (S37); Tipax is asked at most once an hour.
        $parcel = $order->getStatus() === OrderStatus::OnTheWay && filled($order->getTrackingCode())
            ? $parcels->for($order->getId(), (string) $order->getTrackingCode())
            : null;

        return view('profile::livewire.order-detail', [
            'order' => $order,
            'checkpoints' => OrderCheckpoints::for($order),
            'reference' => $payments->paidReference($order->getId()),
            'parcel' => $parcel,
            // Returns (S27): the request form within 24 hours of delivery, and the order's returns.
            'mayRequestReturn' => $returns->customerMayRequest($order),
            'returnUntil' => $returns->customerRequestUntil($order),
            'returnItems' => $returns->itemsLeftFor($order),
            'returnReasons' => ReturnReason::customerOptions(),
            'returns' => $returns->forCustomer($order),
            'addressOptions' => $this->editing ? $addresses->options($this->customerId()) : [],
        ])->title('سفارش '.$order->getNumber());
    }

    /**
     * @return array{order_item_id: null, quantity: int, reason: string, note: null}
     */
    private static function emptyReturnRow(): array
    {
        return ['order_item_id' => null, 'quantity' => 1, 'reason' => ReturnReason::Damaged->value, 'note' => null];
    }
}
