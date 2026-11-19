<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Orders\Actions\ApproveOrderReturn;
use App\Modules\Orders\Actions\CancelOrderReturn;
use App\Modules\Orders\Actions\CreateOrderReturn;
use App\Modules\Orders\Actions\InquireOrderReturn;
use App\Modules\Orders\Actions\MarkOrderRefunded;
use App\Modules\Orders\Actions\RecordReturnRefund;
use App\Modules\Orders\Enums\OrderReturnStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
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
use App\Modules\Orders\Queries\OrderReturns;
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
use Livewire\Livewire;

use function Pest\Livewire\livewire;

/*
| Order returns («مرجوعی‌ها», roadmap S27, owner 2026-11-19): several per order over the units left, full or partial,
| draft → approved (Tipax pickup, SMS) or cancelled (at Tipax too), refunds from what the customer paid per unit.
| Tipax answers as its OM API v4 does.
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

function openReturn(Order $order, array $quantities): OrderReturn
{
    return app(CreateOrderReturn::class)($order->refresh(), $quantities, 'آسیب‌دیده', 1);
}

function pricingCalls(): int
{
    return count(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/Pricing')));
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
        ->and($return->items->sole())->getPaidUnit()->toBe(333_333)->getRefundAmount()->toBe(333_333)
        ->and(app(OrderReturns::class)->scope($this->order))->toBe(ReturnScope::Partial);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/Pricing')
        && count($request['packageInputs']) === 1
        && $request['packageInputs'][0]['packageValue'] === 333_333
        && $request['packageInputs'][0]['pickupType'] === 10
        && $request['packageInputs'][0]['origin']['cityId'] === 1101
        && $request['packageInputs'][0]['destination']['cityId'] === 1199);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/OmOrder'));
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

it('takes a cancelled order that went out back in full, the shipping fee included', function (): void {
    $this->order->setStatus(OrderStatus::Cancelled)->setTrackingCode('HAND-1')->save();

    $return = openReturn($this->order, [$this->cream->getId() => 3, $this->serum->getId() => 1]);

    expect($return)->getScope()->toBe(ReturnScope::Full)->getRefundTotal()->toBe(3_500_000);
});

it('opens no return for an unpaid order, one on its way, one cancelled before it was sent, or with nothing picked', function (): void {
    expect(fn () => openReturn($this->order, []))->toThrow(OrderRefused::class, 'دست‌کم یک کالا');

    $this->order->setStatus(OrderStatus::Pending)->save();
    expect(fn () => openReturn($this->order, [$this->cream->getId() => 1]))->toThrow(OrderRefused::class, 'مرجوعی فقط');

    $this->order->setStatus(OrderStatus::OnTheWay)->save();
    expect(fn () => openReturn($this->order, [$this->cream->getId() => 1]))->toThrow(OrderRefused::class, 'مرجوعی فقط');

    $this->order->setStatus(OrderStatus::Cancelled)->save(); // cancelled before it was sent
    expect(fn () => openReturn($this->order, [$this->cream->getId() => 1]))->toThrow(OrderRefused::class, 'مرجوعی فقط');
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

it('cancels an approved return at Tipax too, and its units may come back again', function (): void {
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

it('follows the pickup with Tipax: picked up, then received at the warehouse', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 1]);
    app(ApproveOrderReturn::class)($return, 1);

    app(InquireOrderReturn::class)($return->refresh());
    expect($return)->getStatus()->toBe(OrderReturnStatus::Approved)->getTipaxStage()->toBe(TipaxParcelStage::AwaitingPickup)->getPickedUpAt()->toBeNull();

    $this->parcelStatusId = 38;
    app(InquireOrderReturn::class)($return);
    expect($return->refresh())->getStatus()->toBe(OrderReturnStatus::PickedUp)->getTipaxStatus()->toBe('جمع آوری شد')->getPickedUpAt()->not->toBeNull();

    $this->parcelStatusId = 50;
    app(CheckOrderReturns::class)->handle(app(InquireOrderReturn::class)); // the nightly check
    expect($return->refresh())->getStatus()->toBe(OrderReturnStatus::Received)->getReceivedAt()->not->toBeNull();
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

it('records the refund only after Tipax picked the return up, and not when Tipax sent it back', function (): void {
    $return = openReturn($this->order, [$this->cream->getId() => 1]);
    app(ApproveOrderReturn::class)($return, 1);

    expect(fn () => app(RecordReturnRefund::class)($return->refresh(), 'REF-1', 1))->toThrow(OrderRefused::class, 'پس از جمع‌آوری');

    $this->parcelStatusId = 51;
    app(InquireOrderReturn::class)($return->refresh());
    expect(fn () => app(RecordReturnRefund::class)($return->refresh(), 'REF-1', 1))->toThrow(OrderRefused::class, 'پس از جمع‌آوری');

    $this->parcelStatusId = 38;
    $other = openReturn($this->order, [$this->serum->getId() => 1]);
    app(ApproveOrderReturn::class)($other, 1);
    app(InquireOrderReturn::class)($other->refresh());
    app(RecordReturnRefund::class)($other->refresh(), ' REF-2 ', 1);

    expect($other->refresh())->getStatus()->toBe(OrderReturnStatus::Refunded)->getRefundReference()->toBe('REF-2')->getRefundedAt()->not->toBeNull();
});

it('refunds a cancelled order that went out only through its return, after pickup', function (): void {
    $this->order->setStatus(OrderStatus::Cancelled)->setPaymentStatus(PaymentStatus::RefundPending)->setTrackingCode('HAND-1')->save();

    expect(fn () => app(MarkOrderRefunded::class)($this->order, 'REF-1', 1_000_000, 0))->toThrow(OrderRefused::class, 'از «ثبت مرجوعی»');

    $return = openReturn($this->order, [$this->cream->getId() => 3, $this->serum->getId() => 1]);
    app(ApproveOrderReturn::class)($return, 1);
    $this->parcelStatusId = 38;
    app(InquireOrderReturn::class)($return->refresh());
    app(RecordReturnRefund::class)($return->refresh(), 'REF-9', 1);

    expect($this->order->refresh())
        ->getPaymentStatus()->toBe(PaymentStatus::Refunded)
        ->getRefundReference()->toBe('REF-9')
        ->getRefundItemsAmount()->toBe(3_000_000)
        ->getRefundShippingAmount()->toBe(500_000);
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
        // A delivered order starts with no units picked.
        Livewire::withQueryParams(['order' => $this->order->getId()])
            ->test(CreateOrderReturnPage::class)
            ->assertSet('data.order_id', $this->order->getId())
            ->assertSet('data.items', fn (array $items): bool => array_column($items, 'title') === ['کرم اوره', 'سرم ویتامین سی']
                && array_column($items, 'quantity') === [0, 0]);
    });

    it('saves the picked units as a draft and opens it', function (): void {
        Repeater::fake();

        livewire(CreateOrderReturnPage::class)
            ->set('data.order_id', $this->order->getId())
            ->set('data.items.0.quantity', 2)
            ->set('data.reason', 'تاریخ نزدیک')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(OrderReturnResource::getUrl('view', ['record' => OrderReturn::query()->sole()]));

        expect(OrderReturn::query()->sole())->getReason()->toBe('تاریخ نزدیک')->getItemsRefund()->toBe(666_666);
    });

    it('refuses more units than are left', function (): void {
        Repeater::fake();

        livewire(CreateOrderReturnPage::class)
            ->set('data.order_id', $this->order->getId())
            ->set('data.items.0.quantity', 4)
            ->call('create')
            ->assertHasFormErrors(['items.0.quantity']);

        expect(OrderReturn::query()->count())->toBe(0);
    });

    it('approves, follows Tipax on the return\'s row and refunds after pickup, with the badge on the order\'s page', function (): void {
        $return = openReturn($this->order, [$this->cream->getId() => 1]);

        livewire(ViewOrderReturn::class, ['record' => $return->getRouteKey()])
            ->assertSee('مرجوعی جزئی')
            ->assertSee('۲۵٬۰۰۰ تومان')
            ->callAction('approve')
            ->assertNotified('مرجوعی در تیپاکس ثبت شد.');
        expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::Approved);

        livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])->assertSee('مرجوعی جزئی');
        $this->parcelStatusId = 38;
        livewire(OrderReturnsRelationManager::class, ['ownerRecord' => $this->order, 'pageClass' => ViewOrder::class])
            ->assertCanSeeTableRecords([$return])
            ->assertSee('PH-140508-000300-R1')
            ->assertSee('در انتظار جمع‌آوری')
            ->callTableAction('inquire', $return)
            ->assertNotified()
            ->assertSee('جمع‌آوری شد');
        expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::PickedUp);

        livewire(ViewOrderReturn::class, ['record' => $return->getRouteKey()])
            ->assertActionHidden('cancel')
            ->callAction('recordRefund', ['reference' => 'REF-7'])
            ->assertNotified('بازگشت وجه ثبت شد.');
        expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::Refunded);
    });

    it('cancels a draft on the return\'s page', function (): void {
        $return = openReturn($this->order, [$this->cream->getId() => 1]);

        livewire(ViewOrderReturn::class, ['record' => $return->getRouteKey()])
            ->callAction('cancel', ['reason' => 'اشتباه'])
            ->assertNotified('مرجوعی لغو شد.');
        expect($return->refresh()->getStatus())->toBe(OrderReturnStatus::Cancelled);
    });
});
