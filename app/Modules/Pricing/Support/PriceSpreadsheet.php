<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Support;

use App\Modules\Shared\Support\PersianNormalizer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * The supplier price Excel (roadmap D13.9, S16): its columns, the template (filled with the current supplier
 * prices, plus a guide sheet), reading an upload, and the error report. Money in the file is toman.
 */
final class PriceSpreadsheet
{
    public const int MAX_ROWS = 10_000;

    public const int PROVIDER_ID = 0;

    public const int PROVIDER_NAME = 1;

    public const int SKU = 2;

    public const int BARCODE = 3;

    public const int PRODUCT_NAME = 4;

    public const int COST = 5;

    public const int IS_AVAILABLE = 6;

    public const int IS_ENABLED = 7;

    public const int LEAD_TIME = 8;

    public const array HEADER = [
        'شناسه تأمین‌کننده',
        'نام تأمین‌کننده (فقط برای راهنما)',
        'کد SKU',
        'بارکد',
        'نام محصول (فقط برای راهنما)',
        'قیمت خرید (تومان)',
        'موجود نزد تأمین‌کننده',
        'فروش از این تأمین‌کننده',
        'زمان تحویل (ساعت)',
    ];

    public const array GUIDE = [
        ['ستون', 'توضیح'],
        ['شناسه تأمین‌کننده', 'الزامی. شناسه عددی تأمین‌کننده (در فهرست تأمین‌کنندگان). یک فایل می‌تواند چند تأمین‌کننده داشته باشد.'],
        ['نام تأمین‌کننده', 'فقط برای راهنما؛ خوانده نمی‌شود.'],
        ['کد SKU', 'کد SKU خود ما. اگر خالی باشد، بارکد استفاده می‌شود.'],
        ['بارکد', 'وقتی کد SKU خالی است برای پیدا کردن SKU به کار می‌رود.'],
        ['نام محصول', 'فقط برای راهنما؛ خوانده نمی‌شود.'],
        ['قیمت خرید (تومان)', 'الزامی. قیمتی که از این تأمین‌کننده می‌خریم، به تومان.'],
        ['موجود نزد تأمین‌کننده', 'بله یا خیر. خالی: مقدار فعلی (برای ردیف جدید: بله).'],
        ['فروش از این تأمین‌کننده', 'بله یا خیر. خالی: مقدار فعلی (برای ردیف جدید: بله).'],
        ['زمان تحویل (ساعت)', 'اختیاری. عدد ساعت. خالی: مقدار فعلی.'],
        ['', ''],
        ['نکته', 'ردیف‌هایی که در فایل نیستند تغییری نمی‌کنند؛ ورود از اکسل هیچ تأمین‌کننده‌ای را از SKU حذف نمی‌کند.'],
        ['نکته', 'پیش از اعمال، پیش‌نمایش تغییرات و ردیف‌های مشکل‌دار نمایش داده می‌شود.'],
    ];

    /**
     * Writes the template: one row per supplier price given, then the guide sheet.
     *
     * @param  iterable<list<string|int>>  $rows  values in HEADER order
     */
    public static function writeTemplate(string $path, iterable $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('قیمت‌ها');
        $writer->addRow(Row::fromValues(self::HEADER));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('راهنما');

        foreach (self::GUIDE as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    /**
     * The error report: the failed rows as read, with the reason in a last column.
     *
     * @param  iterable<array{0: list<string>, 1: string}>  $rows  [cells, reason]
     */
    public static function writeErrors(string $path, iterable $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([...self::HEADER, 'خطا']));

        foreach ($rows as [$cells, $reason]) {
            $writer->addRow(Row::fromValues([...array_pad($cells, count(self::HEADER), ''), $reason]));
        }

        $writer->close();
    }

    /**
     * The rows of the first sheet, without the header and empty rows: row number => cells (trimmed strings).
     *
     * @return array<int, list<string>>
     *
     * @throws RuntimeException when the file has too many rows
     */
    public static function read(string $path, string $extension): array
    {
        $reader = strtolower($extension) === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    /** @var Row $row */
                    $cells = array_map(
                        fn (mixed $value): string => trim(is_scalar($value) ? (string) $value : ''),
                        array_pad($row->toArray(), count(self::HEADER), ''),
                    );

                    if ($number === 1 && str_contains($cells[self::PROVIDER_ID], 'تأمین')) {
                        continue;
                    }

                    if (implode('', $cells) === '') {
                        continue;
                    }

                    $rows[(int) $number] = array_slice($cells, 0, count(self::HEADER));

                    if (count($rows) > self::MAX_ROWS) {
                        throw new RuntimeException('فایل بیش از '.number_format(self::MAX_ROWS).' ردیف دارد.');
                    }
                }

                break; // Only the first sheet; the second is the guide.
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * «بله»/«خیر» (also yes/no, 1/0, true/false); null for blank, false-y garbage is refused by the caller.
     */
    public static function bool(string $value): ?bool
    {
        $value = mb_strtolower(PersianNormalizer::digits(trim($value)));

        return match (true) {
            $value === '' => null,
            in_array($value, ['بله', 'آره', 'هست', 'دارد', 'yes', 'y', '1', 'true', '✓'], true) => true,
            in_array($value, ['خیر', 'نه', 'نیست', 'ندارد', 'no', 'n', '0', 'false', '✗'], true) => false,
            default => throw new \InvalidArgumentException($value),
        };
    }

    /**
     * A whole number from a cell that may use Persian digits and thousands separators; null for blank.
     */
    public static function integer(string $value): ?int
    {
        $value = str_replace([',', '٬', '،', ' '], '', PersianNormalizer::digits(trim($value)));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+(\.0+)?$/', $value) !== 1) {
            throw new \InvalidArgumentException($value);
        }

        return (int) $value;
    }
}
