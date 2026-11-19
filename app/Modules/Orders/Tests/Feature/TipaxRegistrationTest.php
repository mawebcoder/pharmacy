<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Orders\Actions\RegisterOrderReturn;
use App\Modules\Orders\Actions\ShipOrderWithTipax;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\SourcingStatus;
use App\Modules\Orders\Events\OrderOnItsWay;
use App\Modules\Orders\Events\OrderReturnRegistered;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Models\Order;
use App\Modules\Settings\Actions\SaveStoreSettings;
use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shipping\Enums\ParcelContent;
use App\Modules\Shipping\Enums\TipaxShipmentKind;
use App\Modules\Shipping\Enums\TipaxShipmentStatus;
use App\Modules\Shipping\Models\OrderParcel;
use App\Modules\Shipping\Models\TipaxShipment;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

/*
| Registering orders with Tipax at «تحویل به حمل», their callbacks, and returns of cancelled orders (roadmap S35,
| owner 2026-11-19). Answers shaped as Tipax's OmV4OrderListDto.
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
    app(SaveStoreSettings::class)([
        StoreSettings::SHIPPING_ORIGIN_CITY_ID => 1199,
        StoreSettings::SHIPPING_SENDER_NAME => 'انبار هوتانا',
        StoreSettings::SHIPPING_SENDER_MOBILE => '09121112233',
        StoreSettings::SHIPPING_SENDER_ADDRESS => 'کرج، خیابان بهار، پلاک ۱۰',
    ]);
    $this->refuse = false;
    $this->nextCode = 'TIPAX-0001';
    Http::fake(fn (Request $request) => match (true) {
        str_ends_with($request->url(), 'Account/token') => Http::response(['accessToken' => 'token-123', 'expiresIn' => 3600]),
        $this->refuse => Http::response(['isSuccess' => false, 'statusCode' => 'ServiceNotAvailable', 'message' => 'سرویس در این مسیر فعال نیست'], 400),
        default => Http::response(['trackingCodesWithTitles' => [['trackingCode' => $this->nextCode, 'customTitle' => 'x']], 'correlationId' => 'corr-123']),
    });
    $this->order = Order::factory()->paid()->create([
        Order::COLUMN_NUMBER => 'PH-140508-000200',
        Order::COLUMN_STATUS => OrderStatus::Processing,
        Order::COLUMN_SOURCING_STATUS => SourcingStatus::ReadyToPack,
        Order::COLUMN_ADDRESS_SNAPSHOT => ['title' => 'خانه', 'city_id' => 1101, 'province' => 'اصفهان', 'city' => 'اصفهان', 'main_street' => 'چهارباغ', 'plaque' => '12', 'postal_code' => '8143913111', 'receiver_name' => 'سارا رضایی', 'receiver_mobile' => '09123456789'],
        Order::COLUMN_SHIPPING_QUOTE_SNAPSHOT => ['label' => 'تیپاکس اکسپرس', 'method' => 'tipax', 'details' => ['service_id' => 1]],
    ]);
    (new OrderParcel)->setOrderId($this->order->getId())->setSequence(1)->setBoxId(3)
        ->setBoxSnapshot(['name' => 'کارتن سایز 3', 'length_cm' => 20, 'width_cm' => 20, 'height_cm' => 15, 'empty_weight_g' => 150, 'tipax_size' => 3])
        ->setItems([])->setItemsWeightG(1200)->setTotalWeightG(1350)->setDeclaredValue(2_000_000)
        ->setContentType(ParcelContent::General)->setTipaxContentId(37)->setIsEstimate(false)->save();
});

function registered(string $kind = 'outbound'): ?TipaxShipment
{
    return TipaxShipment::query()->where(TipaxShipment::COLUMN_KIND, $kind)->first();
}

it('registers the packed parcels with Tipax and sends the order on its way with Tipax\'s code', function (): void {
    Event::fake([OrderOnItsWay::class]);

    $codes = app(ShipOrderWithTipax::class)($this->order, 1);

    expect($codes)->toBe(['TIPAX-0001'])
        ->and($this->order->refresh())->getStatus()->toBe(OrderStatus::OnTheWay)->getTrackingCode()->toBe('TIPAX-0001')
        ->and(registered())->getStatus()->toBe(TipaxShipmentStatus::Registered)->getCorrelationId()->toBe('corr-123');
    Event::assertDispatched(OrderOnItsWay::class);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/OmOrder')
        && $request['traceCode'] === $this->order->getNumber()
        && $request['packages'][0]['serviceId'] === 1
        && $request['packages'][0]['packageContentId'] === 37
        && $request['packages'][0]['pickupType'] === 20
        && $request['packages'][0]['origin']['cityId'] === 1199
        && $request['packages'][0]['destination']['beneficiary'] === ['fullName' => 'سارا رضایی', 'mobile' => '09123456789']
        && str_contains($request['successCallbackUrl'], '/webhooks/tipax/order/success?secret=hook-secret'));
});

it('leaves the order as it is when Tipax refuses, and lets staff try again', function (): void {
    $this->refuse = true;

    expect(fn () => app(ShipOrderWithTipax::class)($this->order, 1))->toThrow(OrderRefused::class, 'سرویس در این مسیر فعال نیست');
    expect($this->order->refresh()->getStatus())->toBe(OrderStatus::Processing)
        ->and(registered()?->getStatus())->toBe(TipaxShipmentStatus::Failed);

    $this->refuse = false;
    expect(app(ShipOrderWithTipax::class)($this->order->refresh(), 1))->toBe(['TIPAX-0001']);
});

it('refuses without the sender\'s details in the store settings', function (): void {
    app(SaveStoreSettings::class)([StoreSettings::SHIPPING_SENDER_ADDRESS => '']);

    expect(fn () => app(ShipOrderWithTipax::class)($this->order, 1))->toThrow(OrderRefused::class, 'اطلاعات فرستنده');
    Http::assertNothingSent();
});

it('keeps Tipax\'s callback on the shipment, only with our secret', function (): void {
    app(ShipOrderWithTipax::class)($this->order, 1);

    $this->postJson('/webhooks/tipax/order/failure?secret=wrong', ['correlationId' => 'corr-123'])->assertForbidden();
    $this->postJson('/webhooks/tipax/order/failure?secret=hook-secret', ['correlationId' => 'corr-123', 'message' => 'آدرس نامعتبر'])->assertOk();

    expect(registered())->getStatus()->toBe(TipaxShipmentStatus::Failed)->getFailureReason()->toBe('آدرس نامعتبر');
});

it('registers a cancelled order\'s return from the customer to the warehouse, and texts its code', function (): void {
    Event::fake([OrderReturnRegistered::class]);
    expect(app(RegisterOrderReturn::class)->applies($this->order))->toBeFalse();

    app(ShipOrderWithTipax::class)($this->order, 1);
    $this->order->refresh()->setStatus(OrderStatus::Cancelled)->save();
    $this->nextCode = 'TIPAX-RET-9';

    expect(app(RegisterOrderReturn::class)($this->order))->toBe(['TIPAX-RET-9'])
        ->and(registered(TipaxShipmentKind::Return->value)?->getTrackingCodes())->toBe(['TIPAX-RET-9']);
    Event::assertDispatched(OrderReturnRegistered::class, fn (OrderReturnRegistered $event): bool => $event->trackingCode === 'TIPAX-RET-9');
    Http::assertSent(fn (Request $request): bool => ($request['traceCode'] ?? null) === $this->order->getNumber().'-R'
        && $request['packages'][0]['pickupType'] === 10
        && $request['packages'][0]['origin']['cityId'] === 1101
        && $request['packages'][0]['destination']['cityId'] === 1199);
});

it('offers «ثبت مرجوعی در تیپاکس» on the admin page only for a cancelled order that went out with Tipax', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');

    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])->assertActionHidden('registerReturn');

    app(ShipOrderWithTipax::class)($this->order, 1);
    $this->order->refresh()->setStatus(OrderStatus::Cancelled)->save();

    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertActionVisible('registerReturn')
        ->callAction('registerReturn')
        ->assertNotified('مرجوعی در تیپاکس ثبت شد.');
});

it('registers a delivered order\'s return once, with «ثبت مرجوعی از مشتری»', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    app(ShipOrderWithTipax::class)($this->order, 1);
    $this->order->refresh()->setStatus(OrderStatus::Delivered)->save();
    $this->nextCode = 'TIPAX-RET-10';

    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertActionHidden('registerReturn')
        ->callAction('registerCustomerReturn')
        ->assertNotified('مرجوعی در تیپاکس ثبت شد.');
    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])->assertActionHidden('registerCustomerReturn');

    expect(registered(TipaxShipmentKind::Return->value)?->getTrackingCodes())->toBe(['TIPAX-RET-10']);
});

it('registers the return of a cancelled order sent with a hand-written code, packing it first', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    OrderParcel::query()->delete();
    $this->order->setStatus(OrderStatus::Cancelled)->setTrackingCode('HAND-123')->save();
    $this->nextCode = 'TIPAX-RET-11';

    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->callAction('registerReturn')
        ->assertNotified('مرجوعی در تیپاکس ثبت شد.');

    expect(registered(TipaxShipmentKind::Return->value)?->getTrackingCodes())->toBe(['TIPAX-RET-11']);
});

it('prints the invoice in place for the admin', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');

    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])->assertActionVisible('printInvoice');
    $this->get(route('orders.invoice.print', $this->order->getId()))->assertOk()->assertSee('PH-140508-000200');
});
