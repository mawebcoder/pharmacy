<?php

declare(strict_types=1);

namespace App\Modules\Search\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Reads and writes the synonyms spreadsheet (.xlsx or .csv): column A is the phrase, every filled cell after it is
 * a synonym, and a cell may hold several separated by «,» or «،». A first row headed «عبارت» or "term" is a header.
 */
final class SynonymSpreadsheet
{
    public const int MAX_ROWS = 2000;

    public const array HEADER = ['عبارت', 'هم‌معنی', 'هم‌معنی', 'هم‌معنی'];

    /**
     * @return array<int, list<string>> row number => [phrase, synonym, …] (cells trimmed, empty ones dropped)
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
                    $cells = self::cells($row->toArray());

                    if ($number === 1 && in_array(mb_strtolower($cells[0] ?? ''), ['عبارت', 'term'], true)) {
                        continue;
                    }

                    if ($cells !== []) {
                        $rows[(int) $number] = $cells;
                    }

                    if (count($rows) > self::MAX_ROWS) {
                        throw new RuntimeException('فایل بیش از '.self::MAX_ROWS.' ردیف دارد.');
                    }
                }

                break; // Only the first sheet.
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * A sample file with the header and two example rows.
     */
    public static function writeTemplate(string $path): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADER));
        $writer->addRow(Row::fromValues(['ویتامین دی', 'ویتامین D', 'D3', 'vitamin d']));
        $writer->addRow(Row::fromValues(['امگا ۳', 'روغن ماهی، omega 3']));
        $writer->close();
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private static function cells(array $values): array
    {
        $cells = [];

        foreach ($values as $index => $value) {
            $text = trim(is_scalar($value) ? (string) $value : '');

            if ($text === '') {
                if ($index === 0) {
                    $cells[] = '';
                }

                continue;
            }

            // The phrase is one cell; synonym cells may hold several.
            $parts = $index === 0 ? [$text] : (preg_split('/\s*[,،]\s*/u', $text) ?: []);

            foreach ($parts as $part) {
                if ($part !== '') {
                    $cells[] = $part;
                }
            }
        }

        return $cells === [''] ? [] : $cells;
    }
}
