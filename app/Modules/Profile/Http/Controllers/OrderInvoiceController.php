<?php

declare(strict_types=1);

namespace App\Modules\Profile\Http\Controllers;

use App\Modules\Orders\Queries\OrderInvoice;
use Illuminate\Http\Response;

/**
 * The printable invoice behind «چاپ فاکتور» on the customer's order page (owner, 2026-11-19): their own paid order,
 * never another's.
 */
final class OrderInvoiceController
{
    public function __invoke(int $order, OrderInvoice $invoices): Response
    {
        return response($invoices->forCustomer((int) auth('customer')->id(), $order) ?? abort(404));
    }
}
