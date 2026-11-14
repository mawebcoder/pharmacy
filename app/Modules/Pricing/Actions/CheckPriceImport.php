<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Data\VariantPricingInput;
use App\Modules\Catalog\Queries\VariantLookup;
use App\Modules\Catalog\Queries\VariantPricingInputs;
use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Pricing\Models\PriceImport;
use App\Modules\Pricing\Models\PriceImportRow;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Queries\ActiveSuppliers;
use App\Modules\Pricing\Support\PriceSpreadsheet;
use App\Modules\Shared\Support\Toman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Reads an uploaded supplier price file and checks every row for the preview (roadmap D13.9): the supplier by id,
 * the SKU by our SKU code then barcode, the cost in toman, the two switches (بله/خیر; blank keeps the current value,
 * or «بله» for a new row), the lead time, the SKU × supplier commission («-» clears it) and «تأمین‌کننده انتخابی»
 * (S15b; one per SKU in a file). Each row keeps its old values and the selling price the new cost would
 * give with the commission. Nothing is saved to the supplier prices here.
 */
final readonly class CheckPriceImport
{
    public function __construct(
        private VariantLookup $lookup,
        private VariantPricingInputs $variants,
        private ActiveSuppliers $activeSuppliers,
    ) {}

    public function __invoke(PriceImport $import): void
    {
        $import->setStatus(PriceImportStatus::Processing)->save();

        try {
            $path = Storage::disk('local')->path($import->getFilePath());
            $cells = PriceSpreadsheet::read($path, pathinfo($import->getOriginalName(), PATHINFO_EXTENSION));
        } catch (Throwable $exception) {
            $import->setStatus(PriceImportStatus::Failed)->setError('فایل خوانده نشد: '.$exception->getMessage())->save();

            return;
        }

        $providers = Provider::query()->pluck(Provider::COLUMN_ID)->map(intval(...))->flip()->all();
        $bySku = $this->lookup->idsBySku(array_values(array_map(fn (array $row): string => mb_strtoupper($row[PriceSpreadsheet::SKU]), $cells)));
        $byBarcode = $this->lookup->idsByBarcode(array_values(array_map(fn (array $row): string => $row[PriceSpreadsheet::BARCODE], $cells)));
        $rows = [];
        $seen = [];

        foreach ($cells as $number => $row) {
            $rows[] = $this->check($import, $number, $row, $providers, $bySku, $byBarcode, $seen);
        }

        $this->addPreview($rows);

        DB::transaction(function () use ($import, $rows): void {
            $import->rows()->delete();
            $import->rows()->saveMany($rows);

            $failed = count(array_filter($rows, fn (PriceImportRow $row): bool => $row->getError() !== null));
            $import->setRowsTotal(count($rows))
                ->setRowsMatched(count($rows) - $failed)
                ->setRowsFailed($failed)
                ->setStatus(PriceImportStatus::Previewed)
                ->setError(null)
                ->save();
        });
    }

    /**
     * @param  list<string>  $cells
     * @param  array<int, int>  $providers  provider id => index
     * @param  array<string, int>  $bySku
     * @param  array<string, int>  $byBarcode
     * @param  array<string, true>  $seen  "provider:variant" already in the file
     */
    private function check(PriceImport $import, int $number, array $cells, array $providers, array $bySku, array $byBarcode, array &$seen): PriceImportRow
    {
        $row = (new PriceImportRow)->setPriceImportId($import->getId())->setRowNumber($number)->setRaw($cells);

        try {
            $providerId = PriceSpreadsheet::integer($cells[PriceSpreadsheet::PROVIDER_ID]);
        } catch (InvalidArgumentException) {
            $providerId = null;
        }

        if ($providerId === null || ! isset($providers[$providerId])) {
            return $row->setError('تأمین‌کننده‌ای با این شناسه پیدا نشد.');
        }

        $row->setProviderId($providerId);
        $sku = mb_strtoupper($cells[PriceSpreadsheet::SKU]);
        $barcode = $cells[PriceSpreadsheet::BARCODE];

        if ($sku !== '' && isset($bySku[$sku])) {
            $row->setVariantId($bySku[$sku])->setMatchedBy(PriceImportRow::MATCHED_BY_SKU);
        } elseif ($sku === '' && $barcode !== '' && isset($byBarcode[mb_strtoupper($barcode)])) {
            $row->setVariantId($byBarcode[mb_strtoupper($barcode)])->setMatchedBy(PriceImportRow::MATCHED_BY_BARCODE);
        } else {
            return $row->setError($sku === '' && $barcode === '' ? 'کد SKU یا بارکد وارد نشده است.' : 'SKUای با این کد یا بارکد پیدا نشد.');
        }

        $key = $providerId.':'.$row->getVariantId();

        if (isset($seen[$key])) {
            return $row->setError('این تأمین‌کننده و SKU در ردیف دیگری از فایل هم آمده است.');
        }

        $seen[$key] = true;

        try {
            $cost = PriceSpreadsheet::integer($cells[PriceSpreadsheet::COST]);
        } catch (InvalidArgumentException) {
            return $row->setError('قیمت خرید باید عدد (تومان) باشد.');
        }

        if ($cost === null || $cost < 1) {
            return $row->setError('قیمت خرید وارد نشده است.');
        }

        try {
            $available = PriceSpreadsheet::bool($cells[PriceSpreadsheet::IS_AVAILABLE]);
            $enabled = PriceSpreadsheet::bool($cells[PriceSpreadsheet::IS_ENABLED]);
        } catch (InvalidArgumentException) {
            return $row->setError('ستون‌های «موجود» و «فروش» فقط «بله» یا «خیر» می‌پذیرند.');
        }

        try {
            $lead = PriceSpreadsheet::integer($cells[PriceSpreadsheet::LEAD_TIME]);
        } catch (InvalidArgumentException) {
            return $row->setError('زمان تحویل باید عدد ساعت باشد.');
        }

        if ($lead !== null && $lead > 2000) {
            return $row->setError('زمان تحویل بیش از ۲۰۰۰ ساعت است.');
        }

        try {
            $commission = PriceSpreadsheet::percent($cells[PriceSpreadsheet::COMMISSION] ?? '');
            $preferred = PriceSpreadsheet::bool($cells[PriceSpreadsheet::IS_PREFERRED] ?? '');
        } catch (InvalidArgumentException) {
            return $row->setError('کمیسیون باید عددی بین ۰ تا ۱۰۰ (یا «-» برای پاک کردن) و «تأمین‌کننده انتخابی» بله یا خیر باشد.');
        }

        if ($preferred === true) {
            $preferredKey = 'preferred:'.$row->getVariantId();

            if (isset($seen[$preferredKey])) {
                return $row->setError('برای این SKU در فایل بیش از یک تأمین‌کننده انتخابی آمده است.');
            }

            $seen[$preferredKey] = true;
        }

        // A cleared commission is kept as -1 until the preview fills the current values.
        $row->setCommissionPercent(is_string($commission) ? -1.0 : $commission)->setIsPreferred($preferred);

        return $row->setCost(Toman::toRial($cost))
            ->setIsAvailable($available)
            ->setIsEnabled($enabled)
            ->setLeadTimeHours($lead);
    }

    /**
     * Fills the current values, blank cells from them, whether each row changes anything, and the selling price.
     *
     * @param  list<PriceImportRow>  $rows
     */
    private function addPreview(array $rows): void
    {
        $matched = array_values(array_filter($rows, fn (PriceImportRow $row): bool => $row->getError() === null));
        $variantIds = array_values(array_unique(array_map(fn (PriceImportRow $row): int => (int) $row->getVariantId(), $matched)));
        $inputs = $this->variants->for($variantIds);
        $productCommissions = $this->activeSuppliers->productCommissions(array_values(array_unique(array_map(fn (VariantPricingInput $input): int => $input->productId, $inputs))));
        $brandCommissions = $this->activeSuppliers->brandCommissions(array_values(array_unique(array_filter(array_map(fn (VariantPricingInput $input): ?int => $input->brandId, $inputs)))));
        $existing = ProviderPrice::query()->whereIn(ProviderPrice::COLUMN_VARIANT_ID, $variantIds)->get()
            ->keyBy(fn (ProviderPrice $price): string => $price->getProviderId().':'.$price->getVariantId());

        foreach ($matched as $row) {
            $current = $existing->get($row->getProviderId().':'.$row->getVariantId());
            $isNew = ! $current instanceof ProviderPrice;
            $commission = match ($row->getCommissionPercent()) {
                null => $current?->getCommissionPercent(),
                -1.0 => null,
                default => $row->getCommissionPercent(),
            };
            $row->setIsNew($isNew)
                ->setOldCommissionPercent($current?->getCommissionPercent())
                ->setOldIsPreferred($current?->isPreferred())
                ->setCommissionPercent($commission)
                ->setIsPreferred($row->getIsPreferred() ?? $current?->isPreferred() ?? false)
                ->setOldCost($current?->getCost())
                ->setOldIsAvailable($current?->isAvailable())
                ->setOldIsEnabled($current?->isEnabled())
                ->setIsAvailable($row->getIsAvailable() ?? $current?->isAvailable() ?? true)
                ->setIsEnabled($row->getIsEnabled() ?? $current?->isEnabled() ?? true)
                ->setLeadTimeHours($row->getLeadTimeHours() ?? $current?->getLeadTimeHours())
                ->setChanges($isNew
                    || $current->getCost() !== $row->getCost()
                    || $current->isAvailable() !== $row->getIsAvailable()
                    || $current->isEnabled() !== $row->getIsEnabled()
                    || $current->getLeadTimeHours() !== $row->getLeadTimeHours()
                    || $current->getCommissionPercent() !== $row->getCommissionPercent()
                    || $current->isPreferred() !== $row->getIsPreferred());

            $input = $inputs[(int) $row->getVariantId()] ?? null;

            if ($input === null) {
                continue;
            }

            [$percent] = ActiveSuppliers::commission(
                (new ProviderPrice)->setCommissionPercent($row->getCommissionPercent()),
                $productCommissions[$input->productId][(int) $row->getProviderId()] ?? null,
                $input->productCommission,
                $input->brandId === null ? null : ($brandCommissions[$input->brandId][(int) $row->getProviderId()] ?? null),
                $input->brandCommission,
            );

            if ($percent !== null) {
                $selling = CalculateVariantPrice::sellingPrice((int) $row->getCost(), $percent);
                $row->setSellingPrice($input->officialPrice !== null && $input->officialPrice > 0 ? min($selling, $input->officialPrice) : $selling);
            }
        }
    }
}
