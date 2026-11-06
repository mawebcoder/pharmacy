<?php

declare(strict_types=1);

use App\Modules\Shared\Support\PersianDigits;

it('converts Latin and Arabic-Indic digits to Persian digits', function (string|int $value, string $expected): void {
    expect(PersianDigits::toPersian($value))->toBe($expected);
})->with([
    'Jalali date' => ['1405/08/15', '۱۴۰۵/۰۸/۱۵'],
    'integer' => [1250, '۱۲۵۰'],
    'Arabic-Indic digits' => ['١٢٣', '۱۲۳'],
    'text without digits stays the same' => ['ویتامین', 'ویتامین'],
]);

it('converts Persian and Arabic-Indic digits to Latin digits', function (string $value, string $expected): void {
    expect(PersianDigits::toLatin($value))->toBe($expected);
})->with([
    'mobile number in Persian digits' => ['۰۹۱۲۱۲۳۴۵۶۷', '09121234567'],
    'mobile number in Arabic-Indic digits' => ['٠٩١٢١٢٣٤٥٦٧', '09121234567'],
    'mixed digits' => ['۱2٣', '123'],
]);
