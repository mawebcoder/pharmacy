<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\OrderActor;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Jobs\MarkDeliveredOrders;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderStatusLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| The nightly check of orders on their way (owner, 2026-11-19): Tipax-delivered ones become «تحویل شد».
*/

beforeEach(function (): void {
    config([
        'shipping.tipax.base_url' => 'https://tipax.test',
        'shipping.tipax.username' => 'shop',
        'shipping.tipax.password' => 'test-password',
        'shipping.tipax.api_key' => 'test-api-key',
    ]);
    Cache::flush();
    // Tipax's answer per tracking code: [status, completion time], or null for a failure.
    $answers = [
        '9400000000000001' => ['تحویل شده', '1405/08/28 14:10'],
        '9400000000000002' => ['در حال تحویل', null],
        '9400000000000003' => ['برگشت خورده', '1405/08/28 11:00'],
        '9400000000000004' => null,
    ];
    Http::fake(function (Request $request) use ($answers) {
        if (str_ends_with($request->url(), 'Account/token')) {
            return Http::response(['accessToken' => 'token-123', 'expiresIn' => 3600]);
        }

        $answer = $answers[basename($request->url())] ?? null;

        return $answer === null
            ? Http::response('error', 500)
            : Http::response(['contractStatusName' => $answer[0], 'terminateTime' => $answer[1], 'receiverCityName' => 'اصفهان']);
    });
});

function orderOnItsWay(string $trackingCode): Order
{
    return Order::factory()->paid()->create([Order::COLUMN_STATUS => OrderStatus::OnTheWay, Order::COLUMN_TRACKING_CODE => $trackingCode]);
}

it('marks delivered only the orders Tipax delivered, leaving the rest on their way', function (): void {
    $delivered = orderOnItsWay('9400000000000001');
    $onTheWay = orderOnItsWay('9400000000000002');
    $returned = orderOnItsWay('9400000000000003');
    $noAnswer = orderOnItsWay('9400000000000004');
    $notShipped = Order::factory()->paid()->create();

    app()->call([new MarkDeliveredOrders, 'handle']);

    expect($delivered->refresh()->getStatus())->toBe(OrderStatus::Delivered)
        ->and($onTheWay->refresh()->getStatus())->toBe(OrderStatus::OnTheWay)
        ->and($returned->refresh()->getStatus())->toBe(OrderStatus::OnTheWay)
        ->and($noAnswer->refresh()->getStatus())->toBe(OrderStatus::OnTheWay)
        ->and($notShipped->refresh()->getStatus())->toBe(OrderStatus::Paid);

    $log = OrderStatusLog::query()->where(OrderStatusLog::COLUMN_ORDER_ID, $delivered->getId())->latest('id')->first();
    expect($log?->getActorType())->toBe(OrderActor::Carrier)
        ->and($log?->getNote())->toBe('تیپاکس: تحویل شده');
    Http::assertSentCount(5); // sign-in and one tracking call per order on its way
});
