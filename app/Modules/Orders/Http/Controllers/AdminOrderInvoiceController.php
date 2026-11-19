<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\OrderInvoice;
use Illuminate\Http\Response;

/**
 * The printable invoice behind «چاپ فاکتور» on the admin order page (owner, 2026-11-19), for admins who may view the
 * order.
 */
final class AdminOrderInvoiceController
{
    public function __invoke(Order $order, OrderInvoice $invoices): Response
    {
        abort_unless(auth('admin')->user()?->can('view', $order) ?? false, 403);
        abort_unless(OrderInvoice::isAvailable($order), 404);

        return response($invoices->html($order));
    }
}
