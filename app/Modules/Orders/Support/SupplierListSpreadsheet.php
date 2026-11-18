<?php

declare(strict_types=1);

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Models\OrderPurchaseItem;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * The «خرید از تأمین‌کننده» Excel (roadmap S25): purchase document rows still to arrive, by supplier, to send to each
 * supplier. Money in toman.
 */
final class SupplierListSpreadsheet
{
    public const array HEADER = ['تأمین‌کننده', 'سند خرید', 'کالا', 'کد SKU', 'تعداد باقی‌مانده', 'قیمت خرید واحد (تومان)', 'جمع (تومان)'];

    /**
     * @param  iterable<OrderPurchaseItem>  $rows  with orderItem and purchase.order loaded
     */
    public static function write(string $path, iterable $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('خرید از تأمین‌کننده');
        $writer->addRow(Row::fromValues(self::HEADER));

        foreach ($rows as $row) {
            $item = $row->getOrderItem();
            $writer->addRow(Row::fromValues([
                $row->getProviderName(),
                $row->purchase?->getNumber() ?? '',
                trim($item->getProductName().' '.($item->getVariantTitle() ?? '')),
                $item->getSku(),
                $row->getOpenQuantity(),
                intdiv($row->getUnitCost(), 10),
                intdiv($row->getOpenQuantity() * $row->getUnitCost(), 10),
            ]));
        }

        $writer->close();
    }
}
