<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Data\PharmacistData;
use App\Modules\Settings\Models\Pharmacist;
use Illuminate\Support\Facades\DB;

/**
 * Adds a pharmacist (at the end) or changes one (roadmap S35). The store has one pharmacist in charge: marking
 * someone in charge takes it from whoever had it.
 */
final class SavePharmacist
{
    #[\NoDiscard]
    public function __invoke(PharmacistData $data, ?Pharmacist $pharmacist = null): Pharmacist
    {
        return DB::transaction(function () use ($data, $pharmacist): Pharmacist {
            if (! $pharmacist instanceof Pharmacist) {
                $pharmacist = (new Pharmacist)->setSort((int) Pharmacist::query()->max(Pharmacist::COLUMN_SORT) + 1);
            }

            if ($data->isResponsible) {
                foreach (Pharmacist::query()->responsible()->whereKeyNot($pharmacist->getKey() ?? 0)->get() as $other) {
                    $other->setIsResponsible(false)->save();
                }
            }

            $pharmacist->setFullName($data->fullName)
                ->setLicenseNumber($data->licenseNumber)
                ->setBio($data->bio)
                ->setIsResponsible($data->isResponsible)
                ->save();

            return $pharmacist;
        });
    }
}
