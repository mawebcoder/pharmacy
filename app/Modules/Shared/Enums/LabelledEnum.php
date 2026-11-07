<?php

declare(strict_types=1);

namespace App\Modules\Shared\Enums;

use App\Modules\Shared\Contracts\HasLabel;

/**
 * Helpers for backed enums that implement HasLabel.
 *
 * @phpstan-require-implements HasLabel
 */
trait LabelledEnum
{
    /**
     * Value => Persian label, e.g. for Filament selects: ['city' => 'شهر', 'village' => 'روستا'].
     *
     * @return array<string|int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * The Persian label for a stored value, or the value itself when it is not a case.
     */
    public static function labelFor(string|int $value): string
    {
        return self::tryFrom($value)?->label() ?? (string) $value;
    }
}
