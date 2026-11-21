<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Data\ReturnRowData;
use App\Modules\Orders\Enums\ReturnReason;
use App\Modules\Orders\Events\OrderReturnRequested;
use App\Modules\Orders\Exceptions\OrderRefused;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Queries\OrderReturns;

/**
 * «درخواست مرجوعی» from the customer's order page (roadmap S27, owner 2026-11-19): within 24 hours of delivery the
 * customer picks rows of item, units and reason like staff do (not «ناموجود نزد تأمین‌کننده»); the return reaches staff
 * as a draft marked as the customer's, and staff are texted.
 */
final readonly class RequestOrderReturn
{
    public function __construct(
        private OrderReturns $returns,
        private CreateOrderReturn $create,
    ) {}

    /**
     * @param  list<ReturnRowData>  $rows
     *
     * @throws OrderRefused
     */
    public function __invoke(Order $order, int $customerId, array $rows, ?string $note): OrderReturn
    {
        if ($order->getCustomerId() !== $customerId) {
            throw OrderRefused::notYours();
        }

        if (! $this->returns->customerMayRequest($order)) {
            throw OrderRefused::returnWindowClosed();
        }

        foreach ($rows as $row) {
            if (! array_key_exists($row->reason->value, ReturnReason::customerOptions())) {
                throw OrderRefused::returnReasonNotAllowed();
            }
        }

        $return = ($this->create)($order, $rows, $note, null, byCustomer: true);

        OrderReturnRequested::dispatch($order->getId(), $return->getNumber());

        return $return;
    }
}
