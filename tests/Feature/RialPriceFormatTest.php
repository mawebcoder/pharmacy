<?php

namespace Tests\Feature;

use Illuminate\Support\Number;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RialPriceFormatTest extends TestCase
{
    /**
     * @return array<string, array{int|float|string|null, string}>
     */
    public static function prices(): array
    {
        return [
            'below a thousand' => [950, '۹۵۰ ریال'],
            'thousands' => [12_500, '۱۲٬۵۰۰ ریال'],
            'millions' => [4_850_000, '۴٬۸۵۰٬۰۰۰ ریال'],
            'billions' => [1_250_000_000, '۱٬۲۵۰٬۰۰۰٬۰۰۰ ریال'],
            'decimal string from the database' => ['4850000.00', '۴٬۸۵۰٬۰۰۰ ریال'],
            'fractions are rounded' => [999.6, '۱٬۰۰۰ ریال'],
            'null' => [null, '۰ ریال'],
        ];
    }

    #[DataProvider('prices')]
    public function test_prices_are_grouped_in_threes_with_the_rial_unit(int|float|string|null $amount, string $expected): void
    {
        $this->assertSame($expected, Number::rial($amount));
    }

    public function test_the_unit_can_be_left_out(): void
    {
        $this->assertSame('۴٬۸۵۰٬۰۰۰', Number::rial(4_850_000, withUnit: false));
    }
}
