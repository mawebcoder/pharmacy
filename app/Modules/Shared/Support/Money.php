<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use JsonSerializable;

/**
 * An amount of money in whole rial (roadmap C4, D0.1). Immutable: every operation returns a new Money.
 * Amounts may be negative (margins, differences); people see toman via formatToman().
 */
final readonly class Money implements JsonSerializable
{
    private function __construct(private int $rial) {}

    public static function rial(int $rial): self
    {
        return new self($rial);
    }

    public static function toman(int $toman): self
    {
        return new self($toman * 10);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function getRial(): int
    {
        return $this->rial;
    }

    /**
     * Whole toman, rounded half up.
     */
    public function getToman(): int
    {
        return Toman::fromRial($this->rial);
    }

    public function add(self $other): self
    {
        return new self($this->rial + $other->rial);
    }

    public function subtract(self $other): self
    {
        return new self($this->rial - $other->rial);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->rial * $quantity);
    }

    /**
     * A percentage of this amount, rounded half up to whole rial, e.g. 17 % of a price.
     */
    public function percent(float $percent): self
    {
        return new self((int) round($this->rial * $percent / 100));
    }

    public function isZero(): bool
    {
        return $this->rial === 0;
    }

    public function isNegative(): bool
    {
        return $this->rial < 0;
    }

    public function equals(self $other): bool
    {
        return $this->rial === $other->rial;
    }

    public function greaterThan(self $other): bool
    {
        return $this->rial > $other->rial;
    }

    public function lessThan(self $other): bool
    {
        return $this->rial < $other->rial;
    }

    public function min(self $other): self
    {
        return $this->lessThan($other) ? $this : $other;
    }

    /**
     * "۱٬۲۵۰٬۰۰۰ تومان" (or without the unit).
     */
    public function formatToman(bool $withUnit = true): string
    {
        return Toman::format($this->rial, $withUnit);
    }

    /**
     * JSON keeps the exact rial value.
     */
    public function jsonSerialize(): int
    {
        return $this->rial;
    }
}
