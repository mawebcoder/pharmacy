<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;
use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Orders\Actions\ApproveOrderReturn;
use App\Modules\Orders\Actions\CancelOrderReturn;
use App\Modules\Orders\Actions\ConfirmReturnReceived;
use App\Modules\Orders\Actions\CreateOrderReturn;
use App\Modules\Orders\Actions\InquireOrderReturn;
use App\Modules\Orders\Actions\RecordReturnRefund;
use App\Modules\Orders\Actions\RequestOrderReturn;
use App\Modules\Orders\Data\ReturnRowData;
use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\ReturnReason;
use App\Modules\Orders\Enums\ReturnScope;
use App\Modules\Orders\Events\OrderReturnRegistered;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Filament\Resources\Orders\RelationManagers\OrderReturnsRelationManager;
use App\Modules\Orders\Filament\Resources\Returns\OrderReturnResource;
use App\Modules\Orders\Filament\Resources\Returns\Pages\CreateOrderReturn as CreateOrderReturnPage;
use App\Modules\Orders\Filament\Resources\Returns\Pages\ViewOrderReturn;
use App\Modules\Orders\Jobs\CheckOrderReturns;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Models\OrderReturnItem;
use App\Modules\Orders\Queries\OrderReturns;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Profile\Livewire\OrderDetail;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Database\Seeders\TipaxCartonsSeeder;
use App\Modules\Shipping\Enums\TipaxParcelStage;
use App\Modules\Shipping\Models\ShippingBox;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

/*
| Order returns («مرجوعی‌ها», roadmap S27, owner 2026-11-19): several per order over the units left, rows with reasons,
| full or partial; draft → approved (Tipax pickup, SMS) → picked up → received at the warehouse (counted per row) →
| refunded, or cancelled before pickup; refunds from what the customer paid per received unit. Tipax answers as its OM
| API v4 does.
*/

beforeEach(function (): void {
    config([
        'shipping.tipax.base_url' => 'https://tipax.test',
        'shipping.tipax.username' => 'shop',
        'shipping.tipax.password' => 'test-password',
        'shipping.tipax.api_key' => 'test-api-key',
        'shipping.tipax.webhook_secret' => 'hook-secret',
    ]);
    Cache::flush();
    $this->seed(TipaxCartonsSeeder::class);
    app(SaveStoreSettings::class)([
        StoreSettings::SHIPPING_ORIGIN_CITY_ID => 1199,
        StoreSettings::SHIPPING_SENDER_NAME => 'انبار هوتانا',
        StoreSettings::SHIPPING_SENDER_MOBILE => '09121112233',
        StoreSettings::SHIPPING_SENDER_ADDRESS => 'کرج، خیابان بهار، پلاک ۱۰',
    ]);
    $this->refuse = false;
    $this->nextCode = 'TIPAX-RET-1';
    // Tipax's parcel status id for the return's parcels (GET ParcelStatus); null while no branch has handled them.
    $this->parcelStatusId = null;
    // Two services on the way back: express (1) 250,000 and same day (12) 200,000 rial per parcel.
    Http::fake(fn (Request $request) => match (true) {
        str_ends_with($request->url(), 'Account/token') => Http::response(['accessToken' => 'token-123', 'expiresIn' => 3600]),
        $this->refuse => Http::response(['isSuccess' => false, 'statusCode' => 'NotAllowed', 'message' => 'بسته جمع‌آوری شده است'], 400),
        str_ends_with($request->url(), 'GetServicesBetweenCities') => Http::response(['isSuccess' => true, 'data' => ['serviceList' => [
            ['serviceId' => 1, 'serviceKind' => 'اکسپرس', 'deliveryDay' => 4320],
            ['serviceId' => 12, 'serviceKind' => 'همان روز', 'deliveryDay' => 600],
        ]]]),
        str_ends_with($request->url(), '/api/OM/v4/Pricing') => Http::response(array_map(fn (array $package): array => [
            'regularRate' => ['serviceId' => $package['serviceId'], 'finalPrice' => $package['serviceId'] === 12 ? 200_000 : 250_000],
        ], $request['packageInputs'])),
        str_contains($request->url(), 'Parcels/Cancel') => Http::response(['isSuccess' => true]),
        str_ends_with($request->url(), 'Tracking/ByTrackingCode') => Http::response($this->parcelStatusId === null ? [] : array_map(fn (string $code): array => [
            'trackingCode' => $code, 'statusId' => $this->parcelStatusId, 'status' => match ($this->parcelStatusId) {
                38 => 'جمع آوری شد', 50 => 'تحویل به گیرنده', 51 => 'عودت شد', default => 'در دست جمع آوری',
            },
        ], $request->data())),
        default => Http::response(['trackingCodesWithTitles' => [['trackingCode' => $this->nextCode, 'customTitle' => 'x']], 'correlationId' => 'corr-r']),
    });
    $this->order = Order::factory()->paid()->create([
        Order::COLUMN_NUMBER => 'PH-140508-000300',
        Order::COLUMN_STATUS => OrderStatus::Delivered,
        Order::COLUMN_SHIPPING_TOTAL => 500_000,
        Order::COLUMN_ADDRESS_SNAPSHOT => ['title' => 'خانه', 'city_id' => 1101, 'province' => 'اصفهان', 'city' => 'اصفهان', 'main_street' => 'چهارباغ', 'plaque' => '12', 'postal_code' => '8143913111', 'receiver_name' => 'سارا رضایی', 'receiver_mobile' => '09123456789'],
        Order::COLUMN_SHIPPING_QUOTE_SNAPSHOT => ['label' => 'تیپاکس اکسپرس', 'method' => 'tipax', 'details' => ['service_id' => 1]],
    ]);
    // 3 creams paid 1,000,000 rial together (333,333 a unit and a 1 rial remainder), and a 2,000,000 rial serum.
    $this->cream = returnedItem($this->order, 'کرم اوره', 3, 333_333, 1_000_000);
    $this->serum = returnedItem($this->order, 'سرم ویتامین سی', 1, 2_000_000, 2_000_000);
    // What the customer paid at the gateway: refunds never pass it.
    (new Payment)->setOrderId($this->order->getId())->setGateway('fake')->setAmount(3_500_000)->setAuthority('A-PAID')
        ->setUrl('https://gateway.test/A-PAID')->setStatus(PaymentAttemptStatus::Paid)->setExpiresAt(now())->setRefId('REF-PAID')->save();
});

function returnedItem(Order $order, string $name, int $quantity, int $paidUnit, int $paidTotal): OrderItem
{
    $box = ShippingBox::query()->where(ShippingBox::COLUMN_TIPAX_SIZE, 3)->sole();
    $item = (new OrderItem)
        ->setOrderId($order->getId())
        ->setProductName($name)
        ->setSku('SKU-'.fake()->unique()->numerify('#####'))
        ->setQuantity($quantity)
        ->setProductSnapshot(['sku' => ['weight_g' => 200, 'box_capacities' => [$box->getId() => 10], 'ship_separately' => false]])
        ->setListPrice($paidUnit)
        ->setUnitPrice($paidUnit)
        ->setPaidUnit($paidUnit)
        ->setPaidTotal($paidTotal);
    $item->save();

    return $item;
}

/**
 * Opens a return: units per order item, each on one row for «آسیب‌دیده».
 *
 * @param  array<int, int>  $quantities
 */
function openReturn(Order $order, array $quantities): OrderReturn
{
    $rows = [];

    foreach ($quantities as $orderItemId => $quantity) {
        $rows[] = new ReturnRowData($orderItemId, $quantity, ReturnReason::Damaged);
    }

    return app(CreateOrderReturn::class)($order->refresh(), $rows, 'بسته باز شده بود', 1);
}

/**
 * Approves a return and lets Tipax report its parcels picked up, then delivered to us.
 */
function deliverReturn(OrderReturn $return, object $test): OrderReturn
{
    app(ApproveOrderReturn::class)($return->refresh(), 1);
    $test->parcelStatusId = 38;
    app(InquireOrderReturn::class)($return->refresh());
    $test->parcelStatusId = 50;
    app(InquireOrderReturn::class)($return->refresh());

    return $return->refresh();
}

/**
 * Every row of a return received in full.
 *
 * @return array<int, int>
 */
function allReceived(OrderReturn $return): array
{
    return $return->items->mapWithKeys(fn (OrderReturnItem $row): array => [$row->getId() => $row->getQuantity()])->all();
}

it('opens a partial return as a draft, boxing and pricing only the units coming back, without booking Tipax', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 1]);

    expect($return)
        ->getStatus()->toBe(OrderReturnStatus::Draft)
        ->getScope()->toBe(ReturnScope::Partial)
        ->getNumber()->toBe('PH-140508-000300-R1')
        ->getItemsRefund()->toBe(333_333)
        ->getShippingFeeRefund()->toBe(0)
        ->getReturnShippingPrice()->toBe(250_000) // the order's own service, though same day is cheaper
        ->getReturnServiceId()->toBe(1)
        ->and($return->items->sole())->getPaidUnit()->toBe(333_333)->getRefundAmount()->toBe(333_333)->getReason()->toBe(ReturnReason::Damaged)
        ->and(app(OrderReturns::class)->scope($this->order))->toBe(ReturnScope::Partial);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/Pricing')
        && count($request['packageInputs']) === 1
        && $request['packageInputs'][0]['packageValue'] === 333_333
        && $request['packageInputs'][0]['pickupType'] === 10
        && $request['packageInputs'][0]['origin']['cityId'] === 1101
        && $request['packageInputs'][0]['destination']['cityId'] === 1199);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/OmOrder'));
});

it('takes one item on several rows with different reasons, never more than its units left', function (): void {
    $rows = [
        new ReturnRowData($this->cream->getId(), 1, ReturnReason::Damaged, 'درپوش شکسته'),
        new ReturnRowData($this->cream->getId(), 1, ReturnReason::Expiry),
    ];
    $return = app(CreateOrderReturn::class)($this->order, $rows, null, 1);

    expect($return->items)->toHaveCount(2)
        ->and($return->items->pluck(OrderReturnItem::COLUMN_REASON)->all())->toBe([ReturnReason::Damaged, ReturnReason::Expiry])
        ->and($return->items->first()->getReasonNote())->toBe('درپوش شکسته')
        ->and(app(OrderReturns::class)->remaining($this->order)[$this->cream->getId()])->toBe(1);

    expect(fn () => app(CreateOrderReturn::class)($this->order, [
        new ReturnRowData($this->cream->getId(), 1, ReturnReason::Damaged),
        new ReturnRowData($this->cream->getId(), 1, ReturnReason::WrongItem),
    ], null, 1))->toThrow(OrderRefused::class, 'فقط ۱ عدد');
});

it('returns only the units left, puts the remainder on an item\'s last unit and the shipping fee on the return that completes the order', function (): void {
    openReturn($this->order, [$this->cream->getId() => 1]);

    expect(fn () => openReturn($this->order, [$this->cream->getId() => 3]))->toThrow(OrderRefused::class, 'فقط ۲ عدد');

    $last = openReturn($this->order, [$this->cream->getId() => 2, $this->serum->getId() => 1]);

    expect($last)
        ->getScope()->toBe(ReturnScope::Partial)
        ->getItemsRefund()->toBe(2 * 333_333 + 1 + 2_000_000)
        ->getShippingFeeRefund()->toBe(500_000)
        ->and(app(OrderReturns::class)->scope($this->order))->toBe(ReturnScope::Full)
        ->and(app(OrderReturns::class)->totals($this->order))->toBe(['refund' => 333_333 + 2_666_667 + 500_000, 'return_shipping' => 500_000])
        ->and(app(OrderReturns::class)->canReturn($this->order))->toBeFalse();
});

it('opens no return for a cancelled order (refunded on the order), an unpaid one, one on its way, or with nothing picked', function (): void {
    expect(fn () => openReturn($this->order, []))->toThrow(OrderRefused::class, 'دست‌کم یک کالا');

    foreach ([OrderStatus::Cancelled, OrderStatus::Pending, OrderStatus::OnTheWay] as $status) {
        $this->order->setStatus($status)->setTrackingCode('HAND-1')->save();
        expect(fn () => openReturn($this->order, [$this->cream->getId() => 1]))->toThrow(OrderRefused::class, 'مرجوعی فقط');
    }
});

it('books the pickup with Tipax on approval, traced as the return\'s number, and texts the customer the code', function (): void {
    Event::fake([OrderReturnRegistered::class]);
    $first = openReturn($this->order, [$this->cream->getId() => 1]);
    $second = openReturn($this->order, [$this->serum->getId() => 1]);
    $this->nextCode = 'TIPAX-RET-2';

    expect(app(ApproveOrderReturn::class)($second, 1))->toBe(['TIPAX-RET-2'])
        ->and($second->refresh())->getStatus()->toBe(OrderReturnStatus::Approved)->getTrackingCodes()->toBe(['TIPAX-RET-2'])
        ->and(fn () => app(ApproveOrderReturn::class)($second, 1))->toThrow(OrderRefused::class, 'پیش‌نویس');
    Event::assertDispatched(OrderReturnRegistered::class, fn (OrderReturnRegistered $event): bool => $event->trackingCode === 'TIPAX-RET-2');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/OmOrder')
        && $request['traceCode'] === 'PH-140508-000300-R2'
        && count($request['packages']) === 1
        && $request['packages'][0]['packageValue'] === 2_000_000
        && $request['packages'][0]['pickupType'] === 10
        && $request['packages'][0]['origin']['cityId'] === 1101
        && $request['packages'][0]['destination']['cityId'] === 1199);
    expect($first->refresh()->getStatus())->toBe(OrderReturnStatus::Draft);
});

it('keeps the return a draft when Tipax refuses it', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 1]);
    $this->refuse = true;

    expect(fn () => app(ApproveOrderReturn::class)($return, 1))->toThrow(OrderRefused::class, 'بسته جمع‌آوری شده است');
    expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::Draft);
});

it('follows Tipax: picked up, then delivered to us, which lets staff count what arrived', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 1]);
    app(ApproveOrderReturn::class)($return, 1);

    app(InquireOrderReturn::class)($return->refresh());
    expect($return)->getStatus()->toBe(OrderReturnStatus::Approved)->getTipaxStage()->toBe(TipaxParcelStage::AwaitingPickup)->getPickedUpAt()->toBeNull();

    $this->parcelStatusId = 38;
    app(InquireOrderReturn::class)($return);
    expect($return->refresh())->getStatus()->toBe(OrderReturnStatus::PickedUp)->getTipaxStatus()->toBe('جمع آوری شد')->getPickedUpAt()->not->toBeNull()
        ->and($return->isReceivable())->toBeFalse()
        ->and(fn () => app(ConfirmReturnReceived::class)($return, allReceived($return), 1))->toThrow(OrderRefused::class, 'اعلام تحویل');

    $this->parcelStatusId = 50;
    app(CheckOrderReturns::class)->handle(app(InquireOrderReturn::class)); // the nightly check
    expect($return->refresh())->getStatus()->toBe(OrderReturnStatus::PickedUp)->getTipaxStage()->toBe(TipaxParcelStage::Delivered)
        ->and($return->isReceivable())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'Tracking/ByTrackingCode') && $request->data() === ['TIPAX-RET-1']);
});

it('will not cancel a return Tipax has picked up, and never asks Tipax to cancel it', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 1]);
    app(ApproveOrderReturn::class)($return, 1);
    $this->parcelStatusId = 38;

    expect(fn () => app(CancelOrderReturn::class)($return->refresh(), null, 1))->toThrow(OrderRefused::class, 'جمع‌آوری کرده');
    expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::PickedUp);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'Parcels/Cancel'));
});

it('cancels an approved return at Tipax too before pickup, and its units may come back again', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 3, $this->serum->getId() => 1]);
    app(ApproveOrderReturn::class)($return, 1);

    app(CancelOrderReturn::class)($return->refresh(), 'مشتری منصرف شد', 1);

    expect($return->refresh())->getStatus()->toBe(OrderReturnStatus::Cancelled)->getCancelReason()->toBe('مشتری منصرف شد')
        ->and(app(OrderReturns::class)->scope($this->order))->toBeNull()
        ->and(openReturn($this->order, [$this->cream->getId() => 3, $this->serum->getId() => 1])->getShippingFeeRefund())->toBe(500_000);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_ends_with($request->url(), 'Parcels/Cancel/TIPAX-RET-1'));
});

it('keeps an approved return when Tipax will not cancel it', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 1]);
    app(ApproveOrderReturn::class)($return, 1);
    $this->refuse = true;

    expect(fn () => app(CancelOrderReturn::class)($return->refresh(), null, 1))->toThrow(OrderRefused::class, 'بسته جمع‌آوری شده است');
    expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::Approved);
});

it('refunds only the units received, and only once the return is received at the warehouse', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 3, $this->serum->getId() => 1]);
    expect($return)->getItemsRefund()->toBe(3_000_000)->getShippingFeeRefund()->toBe(500_000);

    $return = deliverReturn($return, $this);
    expect(fn () => app(RecordReturnRefund::class)($return, 'REF-1', 1))->toThrow(OrderRefused::class, 'پس از ثبت دریافت');

    [$creams, $serum] = $return->items->all();
    app(ConfirmReturnReceived::class)($return, [$creams->getId() => 2, $serum->getId() => 1], 1);

    expect($return->refresh())
        ->getStatus()->toBe(OrderReturnStatus::Received)
        ->getReceivedAt()->not->toBeNull()
        ->getItemsRefund()->toBe(2 * 333_333 + 2_000_000) // the third cream never arrived: no remainder, no shipping fee
        ->getShippingFeeRefund()->toBe(0)
        ->and($creams->refresh())->getReceivedQuantity()->toBe(2)->getRefundAmount()->toBe(666_666);

    app(RecordReturnRefund::class)($return, ' REF-2 ', 1);
    expect($return->refresh())->getStatus()->toBe(OrderReturnStatus::Refunded)->getRefundReference()->toBe('REF-2')->getRefundedAt()->not->toBeNull();
});

it('refuses to count more units than a row returned', function (): void {
    $return = deliverReturn(openReturn($this->order, [$this->cream->getId() => 1]), $this);

    expect(fn () => app(ConfirmReturnReceived::class)($return, [$return->items->sole()->getId() => 2], 1))->toThrow(OrderRefused::class, 'فقط ۱ عدد');
    expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::PickedUp);
});

it('returns items never sent without Tipax, refunded as soon as the return is approved', function (): void {
    $this->order->setStatus(OrderStatus::Processing)->save();
    $requestsBefore = count(Http::recorded());

    $return = openReturn($this->order, [$this->serum->getId() => 1]);
    expect($return)->needsPickup()->toBeFalse()->getReturnShippingPrice()->toBeNull()->getParcels()->toBe([])
        ->and(fn () => app(RecordReturnRefund::class)($return, 'REF-3', 1))->toThrow(OrderRefused::class);

    expect(app(ApproveOrderReturn::class)($return, 1))->toBe([]);
    app(RecordReturnRefund::class)($return->refresh(), 'REF-3', 1);

    expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::Refunded)
        ->and(count(Http::recorded()))->toBe($requestsBefore);
});

describe('asked for by the customer', function (): void {
    beforeEach(function (): void {
        $this->customer = Customer::factory()->create();
        $this->order->setCustomerId($this->customer->getKey())
            ->setCustomerSnapshot(['name' => 'سارا رضایی', 'mobile' => '09123456789'])
            ->setDeliveredAt(now()->subHours(2))
            ->save();
    });

    it('reaches staff as a draft marked as the customer\'s, and texts the staff mobiles in the settings', function (): void {
        Queue::fake();
        app(SaveStoreSettings::class)([StoreSettings::RETURN_NOTIFY_MOBILES => ['09121110000', '0912 222 0000']]);

        $return = app(RequestOrderReturn::class)($this->order, $this->customer->getKey(), [
            new ReturnRowData($this->cream->getId(), 1, ReturnReason::Damaged, 'درپوش شکسته'),
            new ReturnRowData($this->cream->getId(), 1, ReturnReason::ChangedMind),
        ], 'لطفاً تماس بگیرید');

        expect($return)->getStatus()->toBe(OrderReturnStatus::Draft)->isRequestedByCustomer()->toBeTrue()->getCreatedBy()->toBeNull()
            ->and($return->items)->toHaveCount(2);
        foreach (['09121110000', '09122220000'] as $mobile) {
            Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->template === SmsTemplate::ReturnRequested
                && $job->message->mobile === $mobile
                && $job->message->args === ['سارا رضایی', 'PH-140508-000300', 'PH-140508-000300-R1']);
        }
    });

    it('closes 24 hours after delivery, and only for the customer\'s own delivered order', function (): void {
        $rows = [new ReturnRowData($this->cream->getId(), 1, ReturnReason::Damaged)];

        expect(fn () => app(RequestOrderReturn::class)($this->order, $this->customer->getKey() + 1, $rows, null))->toThrow(OrderRefused::class, 'متعلق به شما نیست')
            ->and(fn () => app(RequestOrderReturn::class)($this->order, $this->customer->getKey(), [new ReturnRowData($this->cream->getId(), 1, ReturnReason::SupplierUnavailable)], null))->toThrow(OrderRefused::class, 'دلیل');

        $this->travel(23)->hours();
        expect(app(OrderReturns::class)->customerMayRequest($this->order))->toBeFalse()
            ->and(fn () => app(RequestOrderReturn::class)($this->order, $this->customer->getKey(), $rows, null))->toThrow(OrderRefused::class, '۲۴ ساعت');

        $this->order->setStatus(OrderStatus::Processing)->setDeliveredAt(null)->save();
        expect(fn () => app(RequestOrderReturn::class)($this->order, $this->customer->getKey(), $rows, null))->toThrow(OrderRefused::class, '۲۴ ساعت');
        expect(OrderReturn::query()->count())->toBe(0);
    });

    it('lets the customer fill rows on the order page and shows the request there', function (): void {
        Queue::fake();
        $this->actingAs($this->customer, 'customer');

        livewire(OrderDetail::class, ['order' => $this->order->getId()])
            ->assertSee('درخواست مرجوعی')
            ->call('startReturn')
            ->set('returnRows.0.order_item_id', $this->cream->getId())
            ->set('returnRows.0.quantity', 2)
            ->set('returnRows.0.reason', ReturnReason::Expiry->value)
            ->call('addReturnRow')
            ->set('returnRows.1.order_item_id', $this->serum->getId())
            ->set('returnRows.1.reason', ReturnReason::WrongItem->value)
            ->call('requestReturn')
            ->assertHasNoErrors()
            ->assertSet('returnRows', [])
            ->assertSee('PH-140508-000300-R1')
            ->assertSee('در انتظار بررسی');

        expect(OrderReturn::query()->sole())->isRequestedByCustomer()->toBeTrue()->getItemsRefund()->toBe(2 * 333_333 + 2_000_000);
    });

    it('hides the request on the order page after 24 hours', function (): void {
        $this->actingAs($this->customer, 'customer');
        $this->travel(23)->hours();

        livewire(OrderDetail::class, ['order' => $this->order->getId()])->assertDontSee('درخواست مرجوعی');
    });
});

describe('in the admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    });

    it('opens a return from the order\'s page with the order filled in', function (): void {
        livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionVisible('createReturn')
            ->assertActionHasUrl('createReturn', OrderReturnResource::getUrl('create', ['order' => $this->order->getId()]));

        $this->get(OrderReturnResource::getUrl('create', ['order' => $this->order->getId()]))->assertOk();
        Livewire::withQueryParams(['order' => $this->order->getId()])
            ->test(CreateOrderReturnPage::class)
            ->assertSet('data.order_id', $this->order->getId())
            ->assertSet('data.items', []);
    });

    it('saves rows of item, units and reason as a draft and opens it', function (): void {
        Repeater::fake();

        livewire(CreateOrderReturnPage::class)
            ->set('data.order_id', $this->order->getId())
            ->set('data.items', [
                ['order_item_id' => $this->cream->getId(), 'quantity' => 1, 'reason' => ReturnReason::Damaged->value, 'note' => 'شکسته'],
                ['order_item_id' => $this->cream->getId(), 'quantity' => 1, 'reason' => ReturnReason::Expiry->value, 'note' => null],
            ])
            ->set('data.reason', 'تماس مشتری')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(OrderReturnResource::getUrl('view', ['record' => OrderReturn::query()->sole()]));

        expect(OrderReturn::query()->sole())->getReason()->toBe('تماس مشتری')->getItemsRefund()->toBe(666_666)
            ->and(OrderReturnItem::query()->count())->toBe(2);
    });

    it('refuses rows of one item that together pass its units left', function (): void {
        Repeater::fake();

        livewire(CreateOrderReturnPage::class)
            ->set('data.order_id', $this->order->getId())
            ->set('data.items', [
                ['order_item_id' => $this->cream->getId(), 'quantity' => 2, 'reason' => ReturnReason::Damaged->value, 'note' => null],
                ['order_item_id' => $this->cream->getId(), 'quantity' => 2, 'reason' => ReturnReason::Expiry->value, 'note' => null],
            ])
            ->call('create')
            ->assertHasFormErrors(['items.0.quantity']);

        expect(OrderReturn::query()->count())->toBe(0);
    });

    it('approves, follows Tipax on the return\'s row, counts what arrived and records the refund, with the badge on the order\'s page', function (): void {
        $return = openReturn($this->order, [$this->cream->getId() => 1]);

        livewire(ViewOrderReturn::class, ['record' => $return->getRouteKey()])
            ->assertSee('مرجوعی جزئی')
            ->assertSee('۲۵٬۰۰۰ تومان')
            ->callAction('approve')
            ->assertNotified('مرجوعی در تیپاکس ثبت شد.');
        expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::Approved);

        livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])->assertSee('مرجوعی جزئی');
        $this->parcelStatusId = 50;
        livewire(OrderReturnsRelationManager::class, ['ownerRecord' => $this->order, 'pageClass' => ViewOrder::class])
            ->assertCanSeeTableRecords([$return])
            ->assertSee('PH-140508-000300-R1')
            ->assertSee('در انتظار جمع‌آوری')
            ->callTableAction('inquire', $return)
            ->assertNotified()
            ->assertSee('تحویل انبار');
        expect($return->refresh())->getStatus()->toBe(OrderReturnStatus::PickedUp)->getTipaxStage()->toBe(TipaxParcelStage::Delivered);

        livewire(ViewOrderReturn::class, ['record' => $return->getRouteKey()])
            ->assertActionHidden('cancel')
            ->assertActionHidden('recordRefund')
            ->callAction('confirmReceived', ['received' => [$return->items->sole()->getId() => 1]])
            ->assertNotified('دریافت در انبار ثبت شد.')
            ->assertActionHidden('recordRefund') // operators do not approve refunds
            ->assertSee('در انتظار تأیید');

        $refund = Payment::query()->where(Payment::COLUMN_ORDER_RETURN_ID, $return->getId())->sole();
        expect($refund)->getAmount()->toBe(333_333)->getStatus()->toBe(PaymentAttemptStatus::Pending);

        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
        livewire(ViewOrderReturn::class, ['record' => $return->getRouteKey()])
            ->callAction('recordRefund', ['reference' => 'REF-7'])
            ->assertNotified('بازگشت وجه ثبت شد.');
        expect($return->refresh())->getStatus()->toBe(OrderReturnStatus::Refunded)->getRefundReference()->toBe('REF-7')
            ->and($refund->refresh()->getStatus())->toBe(PaymentAttemptStatus::Paid);
    });

    it('cancels a draft on the return\'s page', function (): void {
        $return = openReturn($this->order, [$this->cream->getId() => 1]);

        livewire(ViewOrderReturn::class, ['record' => $return->getRouteKey()])
            ->callAction('cancel', ['reason' => 'اشتباه'])
            ->assertNotified('مرجوعی لغو شد.');
        expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::Cancelled);
    });
});
