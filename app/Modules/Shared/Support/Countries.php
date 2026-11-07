<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use Collator;
use ResourceBundle;

/**
 * Countries as ISO 3166-1 alpha-2 codes with their Persian names, from the ICU data in PHP's intl extension.
 */
final class Countries
{
    /**
     * Regions in ICU's list that are not countries (EU, UN, private-use and unknown codes).
     */
    private const array NOT_COUNTRIES = ['EU', 'EZ', 'UN', 'ZZ', 'QO'];

    /** @var array<string, string>|null */
    private static ?array $options = null;

    /**
     * ISO code => Persian name, sorted in Persian alphabetical order: ['DE' => 'آلمان', …].
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        if (self::$options !== null) {
            return self::$options;
        }

        $options = [];
        $bundle = ResourceBundle::create('fa', 'ICUDATA-region');
        $countries = $bundle?->get('Countries');

        if ($countries instanceof ResourceBundle) {
            foreach ($countries as $code => $name) {
                if (is_string($code) && is_string($name) && self::isCountryCode($code)) {
                    $options[$code] = $name;
                }
            }
        }

        $collator = new Collator('fa');
        uasort($options, fn (string $first, string $second): int => (int) $collator->compare($first, $second));

        return self::$options = $options;
    }

    /**
     * The Persian name for an ISO code, or null for an unknown code.
     */
    public static function name(?string $code): ?string
    {
        return $code === null ? null : (self::options()[strtoupper($code)] ?? null);
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::options());
    }

    private static function isCountryCode(string $code): bool
    {
        return preg_match('/^[A-Z]{2}$/', $code) === 1
            && ! in_array($code, self::NOT_COUNTRIES, true)
            && ! str_starts_with($code, 'X')
            && ! str_starts_with($code, 'Q');
    }
}
