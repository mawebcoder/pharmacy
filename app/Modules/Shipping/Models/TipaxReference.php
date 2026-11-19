<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Models;

use App\Modules\Shipping\Enums\TipaxReferenceType;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of a Tipax list (roadmap S33): a service, pack type, packing or package content, by Tipax's id, with the
 * pack type it belongs to. Written only by `tipax:sync-reference`, never by hand, so it is not audited.
 */
final class TipaxReference extends Model
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_TYPE = 'type';

    public const string COLUMN_EXTERNAL_ID = 'external_id';

    public const string COLUMN_TITLE = 'title';

    public const string COLUMN_PACK_TYPE = 'pack_type';

    public const string COLUMN_DATA = 'data';

    public const string COLUMN_SYNCED_AT = 'synced_at';

    public const string COLUMN_CREATED_AT = 'created_at';

    public const string COLUMN_UPDATED_AT = 'updated_at';

    public function getType(): TipaxReferenceType
    {
        return $this->getAttribute(self::COLUMN_TYPE);
    }

    public function getExternalId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_EXTERNAL_ID);
    }

    public function getTitle(): string
    {
        return (string) $this->getAttribute(self::COLUMN_TITLE);
    }

    public function getPackType(): ?int
    {
        $packType = $this->getAttribute(self::COLUMN_PACK_TYPE);

        return $packType === null ? null : (int) $packType;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_TYPE => TipaxReferenceType::class,
            self::COLUMN_DATA => 'array',
            self::COLUMN_SYNCED_AT => 'datetime',
        ];
    }
}
