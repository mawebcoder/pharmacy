<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Enums\TipaxReferenceType;
use App\Modules\Shipping\Models\TipaxReference;
use Illuminate\Support\Facades\DB;

/**
 * Copies Tipax's lists into tipax_references (roadmap D11.3 step 7, S33): services, pack types, packings and package
 * contents, keyed by type and Tipax's id. Titles and pack types follow Tipax; entries Tipax stops listing are kept, so
 * a saved choice never points at nothing. Rows without an id or title are skipped.
 */
final class SyncTipaxReferences
{
    /**
     * @param  array<value-of<TipaxReferenceType>, list<array<string, mixed>>>  $lists  Tipax's rows per type
     * @return array<value-of<TipaxReferenceType>, int> how many rows of each type were saved
     */
    public function __invoke(array $lists): array
    {
        $now = now();
        $saved = [];

        DB::transaction(function () use ($lists, $now, &$saved): void {
            foreach ($lists as $type => $rows) {
                $records = [];

                foreach ($rows as $row) {
                    $id = $row['id'] ?? null;
                    $title = is_string($row['title'] ?? null) ? trim($row['title']) : '';

                    if (! is_numeric($id) || $title === '') {
                        continue;
                    }

                    $records[(int) $id] = [
                        TipaxReference::COLUMN_TYPE => $type,
                        TipaxReference::COLUMN_EXTERNAL_ID => (int) $id,
                        TipaxReference::COLUMN_TITLE => mb_substr($title, 0, 255),
                        TipaxReference::COLUMN_PACK_TYPE => is_numeric($row['packType'] ?? null) ? (int) $row['packType'] : null,
                        TipaxReference::COLUMN_DATA => json_encode($row, JSON_UNESCAPED_UNICODE),
                        TipaxReference::COLUMN_SYNCED_AT => $now,
                        TipaxReference::COLUMN_CREATED_AT => $now,
                        TipaxReference::COLUMN_UPDATED_AT => $now,
                    ];
                }

                TipaxReference::query()->upsert(array_values($records), [TipaxReference::COLUMN_TYPE, TipaxReference::COLUMN_EXTERNAL_ID], [
                    TipaxReference::COLUMN_TITLE, TipaxReference::COLUMN_PACK_TYPE, TipaxReference::COLUMN_DATA, TipaxReference::COLUMN_SYNCED_AT, TipaxReference::COLUMN_UPDATED_AT,
                ]);
                $saved[$type] = count($records);
            }
        });

        return $saved;
    }
}
