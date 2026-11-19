<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\OrderInvoice;

/*
| «فاکتور فروش» as a printable page (owner, 2026-11-19): «چاپ فاکتور» loads it in a hidden frame and opens the print
| dialog, for the admin and for the customer's own paid orders.
*/

beforeEach(function (): void {
    $this->customer = Customer::factory()->create();
    $this->order = Order::factory()->paid()->create([
        Order::COLUMN_CUSTOMER_ID => $this->customer->getKey(),
        Order::COLUMN_NUMBER => 'PH-140508-000300',
        Order::COLUMN_TRACKING_CODE => '940512345678901234',
    ]);
});

it('renders the invoice with the order number, tracking code and its barcode', function (): void {
    expect(app(OrderInvoice::class)->html($this->order))
        ->toContain('فاکتور فروش', 'PH-140508-000300', '940512345678901234', '<svg');
});

it('shows the customer their own paid order\'s invoice, and nobody else\'s', function (): void {
    $this->actingAs($this->customer, 'customer');

    $this->get(route('account.orders.invoice', $this->order->getId()))
        ->assertOk()
        ->assertSee('PH-140508-000300');

    $someoneElses = Order::factory()->paid()->create();
    $this->get(route('account.orders.invoice', $someoneElses->getId()))->assertNotFound();

    $unpaid = Order::factory()->create([Order::COLUMN_CUSTOMER_ID => $this->customer->getKey()]);
    $this->get(route('account.orders.invoice', $unpaid->getId()))->assertNotFound();
});

it('asks a guest to sign in', function (): void {
    $this->get(route('account.orders.invoice', $this->order->getId()))->assertRedirect(route('login'));
});

it('keeps the admin invoice behind the admin sign-in', function (): void {
    $this->actingAs($this->customer, 'customer');

    $this->get(route('orders.invoice.print', $this->order->getId()))->assertRedirect();
});
