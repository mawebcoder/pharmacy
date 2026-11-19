<?php

declare(strict_types=1);

use App\Modules\Notifications\Enums\SmsTemplate;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Orders\Events\OrderOnItsWay;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Queue;

/*
| Order news by SMS (roadmap S37, owner 2026-11-19): «پرداخت سفارش» and «ارسال سفارش» with the Tipax tracking code.
*/

beforeEach(function (): void {
    Queue::fake();
});

it('texts the customer their name and the order number when the order is paid', function (): void {
    $order = Order::factory()->paid()->create([Order::COLUMN_NUMBER => 'PH-140508-000123', Order::COLUMN_CUSTOMER_SNAPSHOT => ['name' => 'سارا رضایی', 'mobile' => '09123456789']]);

    event(new OrderPaid($order->getId()));

    Queue::assertPushedOn(SendSmsJob::QUEUE, SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->template === SmsTemplate::OrderPaid
        && $job->message->mobile === '09123456789'
        && $job->message->args === ['سارا رضایی', 'PH-140508-000123']);
});

it('texts the name, order number and Tipax tracking code when the order is on its way', function (): void {
    $order = Order::factory()->paid()->create([Order::COLUMN_NUMBER => 'PH-140508-000124', Order::COLUMN_TRACKING_CODE => '940512345678901234']);

    event(new OrderOnItsWay($order->getId()));

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->template === SmsTemplate::OrderOnTheWay
        && $job->message->args === ['سارا رضایی', 'PH-140508-000124', '940512345678901234']);
});

it('keeps trying order news for an hour, but a sign-in code only for a minute', function (): void {
    expect(SmsTemplate::OrderPaid->expiresAfterSeconds())->toBe(3600)
        ->and(SmsTemplate::Otp->expiresAfterSeconds())->toBe(60)
        ->and(SmsTemplate::OrderOnTheWay->isSecret())->toBeFalse();
});

it('calls a customer who gave no name «مشتری»', function (): void {
    $order = Order::factory()->paid()->create([Order::COLUMN_NUMBER => 'PH-140508-000125', Order::COLUMN_CUSTOMER_SNAPSHOT => ['name' => null, 'mobile' => '09123456789']]);

    event(new OrderPaid($order->getId()));

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->message->args === ['مشتری', 'PH-140508-000125']);
});
