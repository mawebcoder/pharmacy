<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Shared\Support\PersianDigits;

/**
 * Builds an address's text from its parts (owner, 2026-11-14): one line for cards, summaries and labels, or labelled
 * sections, always in the same order with Persian digits. Empty parts are left out.
 *
 *   AddressText::from(['province' => 'تهران', 'city' => 'تهران', 'main_street' => 'ولیعصر', 'alley' => 'نسترن',
 *       'plaque' => '12', 'floor' => '3', 'unit' => '5', 'postal_code' => '1234567890'])->line()
 *   → «تهران، تهران، خیابان ولیعصر، کوچه نسترن، پلاک ۱۲، طبقه ۳، واحد ۵»
 */
final readonly class AddressText
{
    /**
     * Part => its label (and the prefix in the line), in reading order.
     *
     * @var array<string, string>
     */
    private const array STREET_PARTS = [
        'main_street' => 'خیابان',
        'side_street' => 'خیابان فرعی',
        'alley' => 'کوچه',
        'block' => 'بلوک',
        'plaque' => 'پلاک',
        'floor' => 'طبقه',
        'unit' => 'واحد',
    ];

    /**
     * @param  array<string, string|null>  $parts
     */
    private function __construct(private array $parts) {}

    /**
     * Keys: province, city, main_street, side_street, alley, block, plaque, floor, unit, details, postal_code,
     * receiver_name, receiver_mobile (all optional).
     *
     * @param  array<string, mixed>  $parts
     */
    public static function from(array $parts): self
    {
        $clean = [];

        foreach ($parts as $key => $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            $clean[$key] = $value === '' ? null : $value;
        }

        return new self($clean);
    }

    /**
     * «تهران، تهران، خیابان ولیعصر، کوچه نسترن، بلوک ۲، پلاک ۱۲، طبقه ۳، واحد ۵، روبه‌روی پارک»
     */
    public function line(): string
    {
        $pieces = [$this->parts['province'] ?? null, $this->parts['city'] ?? null];

        foreach (self::STREET_PARTS as $key => $prefix) {
            $value = $this->parts[$key] ?? null;
            $pieces[] = $value === null ? null : $this->withPrefix($prefix, $value);
        }

        $pieces[] = $this->parts['details'] ?? null;

        return PersianDigits::toPersian(implode('، ', array_values(array_filter($pieces, fn (?string $piece): bool => $piece !== null))));
    }

    /**
     * Labelled sections for a detail view: [label, value] in order, only the filled ones.
     *
     * @return list<array{label: string, value: string}>
     */
    public function sections(): array
    {
        $place = implode('، ', array_values(array_filter([$this->parts['province'] ?? null, $this->parts['city'] ?? null])));
        $rows = [
            'استان و شهر' => $place === '' ? null : $place,
            'خیابان اصلی' => $this->parts['main_street'] ?? null,
            'خیابان فرعی' => $this->parts['side_street'] ?? null,
            'کوچه' => $this->parts['alley'] ?? null,
            'بلوک' => $this->parts['block'] ?? null,
            'پلاک' => $this->parts['plaque'] ?? null,
            'طبقه' => $this->parts['floor'] ?? null,
            'واحد' => $this->parts['unit'] ?? null,
            'توضیحات' => $this->parts['details'] ?? null,
            'کد پستی' => $this->parts['postal_code'] ?? null,
            'گیرنده' => $this->parts['receiver_name'] ?? null,
            'تلفن گیرنده' => $this->parts['receiver_mobile'] ?? null,
        ];

        $sections = [];

        foreach ($rows as $label => $value) {
            if ($value !== null) {
                $sections[] = ['label' => $label, 'value' => PersianDigits::toPersian($value)];
            }
        }

        return $sections;
    }

    /**
     * «خیابان ولیعصر», but «ولیعصر» typed as «خیابان ولیعصر» or «کوچه ۵» is not doubled.
     */
    private function withPrefix(string $prefix, string $value): string
    {
        $word = explode(' ', $prefix)[0];

        return str_starts_with($value, $word) ? $value : $prefix.' '.$value;
    }
}
