<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Queries;

use App\Modules\Shipping\Enums\TipaxReferenceType;
use App\Modules\Shipping\Models\TipaxReference;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tipax's lists for admin dropdowns (roadmap S33): Tipax id => title, packings and contents limited to one pack type
 * (Tipax refuses a packing or content of another pack type).
 */
final readonly class TipaxReferences
{
    /**
     * @return array<int, string>
     */
    public function options(TipaxReferenceType $type, ?int $packType = null): array
    {
        return TipaxReference::query()
            ->where(TipaxReference::COLUMN_TYPE, $type)
            ->when($packType !== null, fn (Builder $query): Builder => $query->where(TipaxReference::COLUMN_PACK_TYPE, $packType))
            ->orderBy(TipaxReference::COLUMN_EXTERNAL_ID)
            ->get()
            ->mapWithKeys(fn (TipaxReference $reference): array => [$reference->getExternalId() => $reference->getTitle().' ('.$reference->getExternalId().')'])
            ->all();
    }
}
