<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Identity\Models\Customer;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Orders\Models\Order;
use App\Modules\Profile\Livewire\OrderDetail;
use App\Modules\Shipping\Queries\ParcelTrackingStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

/*
| A parcel's progress on the customer's order page from Tipax's BriefTracking, asked at most once an hour (roadmap S37,
| owner 2026-11-19).
*/

beforeEach(function (): void {
    config([
        'shipping.tipax.base_url' => 'https://tipax.test',
        'shipping.tipax.username' => 'shop',
        'shipping.tipax.password' => 'test-password',
        'shipping.tipax.api_key' => 'test-api-key',
    ]);
    Cache::flush();
    $this->tipaxDown = false;
    $this->notReceived = false;
    $this->status = 'در حال توزیع';
    Http::fake(fn (Request $request) => match (true) {
        str_ends_with($request->url(), 'Account/token') => Http::response(['accessToken' => 'token-123', 'expiresIn' => 3600]),
        $this->tipaxDown => Http::response('error', 500),
        $this->notReceived => Http::response('null'),
        default => Http::response([
            'contractCode' => '940512345678901234', 'contractStatusName' => $this->status, 'receiverCityName' => 'اصفهان',
            'terminateBranch' => null, 'assignedAgencyName' => 'نمایندگی اصفهان مرکزی', 'terminateTime' => null,
        ]),
    });
    $this->customer = Customer::factory()->create();
    $this->order = Order::factory()->paid()->create([
        Order::COLUMN_CUSTOMER_ID => $this->customer->getKey(),
        Order::COLUMN_STATUS => OrderStatus::OnTheWay,
        Order::COLUMN_TRACKING_CODE => '940512345678901234',
    ]);
});

function trackingCalls(): int
{
    return count(Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'BriefTracking')));
}

it('shows Tipax\'s status on the order page and asks Tipax at most once an hour', function (): void {
    $this->actingAs($this->customer, 'customer');

    livewire(OrderDetail::class, ['order' => $this->order->getId()])
        ->assertSee('وضعیت مرسوله')
        ->assertDontSee('تیپاکس')
        ->assertSee('در حال توزیع')
        ->assertSee('نمایندگی اصفهان مرکزی');
    livewire(OrderDetail::class, ['order' => $this->order->getId()]);

    expect(trackingCalls())->toBe(1);

    $this->status = 'تحویل شد';
    $this->travel(61)->minutes();
    livewire(OrderDetail::class, ['order' => $this->order->getId()])->assertSee('تحویل شد');

    expect(trackingCalls())->toBe(2);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/OM/v4/Tracking/BriefTracking/940512345678901234'));
});

it('keeps showing the last answer when Tipax fails, and waits the hour before asking again', function (): void {
    $tracking = app(ParcelTrackingStatus::class);
    expect($tracking->for($this->order->getId(), '940512345678901234')?->status)->toBe('در حال توزیع');

    $this->tipaxDown = true;
    $this->travel(61)->minutes();

    expect($tracking->for($this->order->getId(), '940512345678901234')?->status)->toBe('در حال توزیع')
        ->and($tracking->for($this->order->getId(), '940512345678901234')?->status)->toBe('در حال توزیع')
        ->and(trackingCalls())->toBe(2);
});

it('does not ask Tipax for an order that is not on its way', function (): void {
    $this->order->setStatus(OrderStatus::Delivered)->save();
    $this->actingAs($this->customer, 'customer');

    livewire(OrderDetail::class, ['order' => $this->order->getId()])->assertDontSee('استعلام از سرویس ارسال');

    expect(trackingCalls())->toBe(0);
});

it('lets an admin ask Tipax now with «استعلام از تیپاکس», whatever the hour, and shows the answer', function (): void {
    Filament\Facades\Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    app(ParcelTrackingStatus::class)->for($this->order->getId(), '940512345678901234');

    $this->status = 'تحویل شد';
    livewire(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->callAction('trackParcel')
        ->assertNotified('وضعیت مرسوله: تحویل شد');

    expect(trackingCalls())->toBe(2)
        ->and(app(ParcelTrackingStatus::class)->for($this->order->getId(), '940512345678901234')?->status)->toBe('تحویل شد');
});

it('lets the customer ask the carrier with «استعلام از سرویس ارسال», an hour after its last successful answer', function (): void {
    $this->actingAs($this->customer, 'customer');
    $page = livewire(OrderDetail::class, ['order' => $this->order->getId()]); // the page's own hourly ask

    $page->call('inquireParcel')->assertSee('دقیقه دیگر دوباره امتحان کنید');
    expect(trackingCalls())->toBe(1);

    $this->status = 'تحویل شد';
    $this->travel(61)->minutes();
    $page->call('inquireParcel')
        ->assertSee('آخرین وضعیت مرسوله از سرویس ارسال دریافت شد.')
        ->assertSee('تحویل شد');
    expect(trackingCalls())->toBe(2);
});

it('lets the customer try again at once when the carrier did not answer', function (): void {
    $this->tipaxDown = true;
    $this->actingAs($this->customer, 'customer');

    $page = livewire(OrderDetail::class, ['order' => $this->order->getId()])
        ->call('inquireParcel')
        ->assertSee('سرویس ارسال پاسخ نداد');
    $this->tipaxDown = false;
    $page->call('inquireParcel')->assertSee('در حال توزیع');

    expect(trackingCalls())->toBe(3); // the page's own ask, the failed one, the retry
});

it('shows the parcel as awaiting pickup while Tipax has no events for it yet', function (): void {
    $this->notReceived = true;
    $this->actingAs($this->customer, 'customer');

    livewire(OrderDetail::class, ['order' => $this->order->getId()])
        ->call('inquireParcel')
        ->assertDontSee('سرویس ارسال پاسخ نداد')
        ->assertSee('در انتظار دریافت مرسوله در شعبه');
});
