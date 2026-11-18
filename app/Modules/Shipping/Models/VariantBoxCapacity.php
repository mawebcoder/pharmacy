<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use App\Modules\Shared\Contracts\HasActivityLogLabel;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How many pieces of a SKU fit one box (roadmap S31, owner 2026-11-18). No row means it does not fit that box.
 */
final class VariantBoxCapacity extends Model implements HasActivityLogLabel
{
    use LogsActivity;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_VARIANT_ID = 'variant_id';

    public const string COLUMN_BOX_ID = 'box_id';

    public const string COLUMN_CAPACITY = 'capacity';

    public function getVariantId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_VARIANT_ID);
    }

    public function setVariantId(int $variantId): static
    {
        return $this->setAttribute(self::COLUMN_VARIANT_ID, $variantId);
    }

    public function getBoxId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_BOX_ID);
    }

    public function setBoxId(int $boxId): static
    {
        return $this->setAttribute(self::COLUMN_BOX_ID, $boxId);
    }

    public function getCapacity(): int
    {
        return (int) $this->getAttribute(self::COLUMN_CAPACITY);
    }

    public function setCapacity(int $capacity): static
    {
        return $this->setAttribute(self::COLUMN_CAPACITY, $capacity);
    }

    public function activityLogLabel(): string
    {
        return 'جا در کارتن';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('shipping')
            ->logOnly([self::COLUMN_VARIANT_ID, self::COLUMN_BOX_ID, self::COLUMN_CAPACITY])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [self::COLUMN_CAPACITY => 'integer'];
    }
}
