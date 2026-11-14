<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Pricing\Actions\ConfirmPriceImport;
use App\Modules\Pricing\Actions\StartPriceImport;
use App\Modules\Pricing\Actions\WritePriceTemplate;
use App\Modules\Pricing\Enums\PriceChangeReason;
use App\Modules\Pricing\Enums\PriceImportStatus;
use App\Modules\Pricing\Exceptions\PriceImportRefused;
use App\Modules\Pricing\Filament\Resources\PriceImports\Pages\ViewPriceImport;
use App\Modules\Pricing\Filament\Resources\Providers\Pages\ListProviders;
use App\Modules\Pricing\Jobs\ApplyPriceImport;
use App\Modules\Pricing\Jobs\ParsePriceImport;
use App\Modules\Pricing\Models\PriceImport;
use App\Modules\Pricing\Models\PriceImportRow;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Support\PriceSpreadsheet;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * A SKU of a product whose brand has a 10 % commission, without a printed price.
 */
function importSku(string $sku, ?string $barcode = null): ProductVariant
{
    $product = Product::factory()->active()->for(Brand::factory()->state([Brand::COLUMN_COMMISSION_PERCENT => 10]))->create();

    return ProductVariant::factory()->for($product)->create([
        ProductVariant::COLUMN_SKU => $sku,
        ProductVariant::COLUMN_BARCODE => $barcode,
        ProductVariant::COLUMN_OFFICIAL_PRICE => null,
    ]);
}

/**
 * An uploaded price file on the local disk, rows in PriceSpreadsheet::HEADER order, checked through the job.
 *
 * @param  list<list<string|int>>  $rows
 */
function importFile(array $rows): PriceImport
{
    $path = 'imports/prices/test-'.uniqid().'.xlsx';
    Storage::disk('local')->put($path, '');
    PriceSpreadsheet::writeTemplate(Storage::disk('local')->path($path), $rows);

    return app(StartPriceImport::class)($path, 'prices.xlsx')->fresh();
}

/**
 * @return list<string|int>
 */
function priceRow(int|string $providerId, string $sku, int|string $costToman, string $available = '', string $enabled = '', string $barcode = '', int|string $lead = ''): array
{
    return [$providerId, '', $sku, $barcode, '', $costToman, $available, $enabled, $lead];
}

describe('template', function (): void {
    it('holds every current supplier price in toman', function (): void {
        $sku = importSku('EUC-0001-01', '6260001');
        $price = ProviderPrice::factory()->forVariant($sku->getId())->create([ProviderPrice::COLUMN_COST => 8_500_000, ProviderPrice::COLUMN_IS_ENABLED => false, ProviderPrice::COLUMN_LEAD_TIME_HOURS => 24]);
        $path = Storage::disk('local')->path('template.xlsx');

        app(WritePriceTemplate::class)($path);

        expect(array_values(PriceSpreadsheet::read($path, 'xlsx')))->toBe([
            [(string) $price->getProviderId(), $price->getProvider()->getName(), 'EUC-0001-01', '6260001', $sku->getProduct()->getNameFa().' · '.$sku->getTitleFa(), '850000', 'بله', 'خیر', '24', '', 'خیر'],
        ]);
    });
});

describe('checking a file', function (): void {
    it('matches rows by SKU code, then barcode, and changes nothing yet', function (): void {
        $provider = Provider::factory()->create();
        $bySku = importSku('EUC-0001-01');
        $byBarcode = importSku('EUC-0002-01', '6260002');
        $existing = ProviderPrice::factory()->forVariant($bySku->getId())->create([ProviderPrice::COLUMN_PROVIDER_ID => $provider->getId(), ProviderPrice::COLUMN_COST => 8_500_000]);

        $import = importFile([
            priceRow($provider->getId(), 'euc-0001-01', '۹۰۰٬۰۰۰'),
            priceRow($provider->getId(), '', '450,000', barcode: '6260002'),
        ]);

        expect($import)
            ->getStatus()->toBe(PriceImportStatus::Previewed)
            ->getRowsTotal()->toBe(2)
            ->getRowsFailed()->toBe(0)
            ->and($import->rows()->orderBy('row_number')->get()->map(fn (PriceImportRow $row): array => [$row->getVariantId(), $row->getMatchedBy(), $row->getCost(), $row->getOldCost(), $row->isNew()])->all())
            ->toBe([
                [$bySku->getId(), 'sku', 9_000_000, 8_500_000, false],
                [$byBarcode->getId(), 'barcode', 4_500_000, null, true],
            ])
            ->and($existing->fresh()->getCost())->toBe(8_500_000)
            ->and(ProviderPrice::query()->count())->toBe(1);
    });

    it('shows the selling price the new cost gives with the commission', function (): void {
        $provider = Provider::factory()->create();
        importSku('EUC-0001-01');

        $import = importFile([priceRow($provider->getId(), 'EUC-0001-01', 850_000)]);

        // 850,000 × 1.10 = 935,000 toman.
        expect($import->rows()->sole()->getSellingPrice())->toBe(9_350_000);
    });

    it('refuses a row it cannot import', function (array $row, string $reason): void {
        $provider = Provider::factory()->create();
        importSku('EUC-0001-01');

        $import = importFile([str_replace('{provider}', (string) $provider->getId(), $row)]);

        expect($import->getRowsFailed())->toBe(1)
            ->and($import->rows()->sole()->getError())->toContain($reason);
    })->with([
        'unknown supplier' => [[999_999, '', 'EUC-0001-01', '', '', 100, '', '', ''], 'تأمین‌کننده'],
        'unknown SKU' => [['{provider}', '', 'NOPE-01', '', '', 100, '', '', ''], 'SKUای با این کد'],
        'no SKU or barcode' => [['{provider}', '', '', '', '', 100, '', '', ''], 'وارد نشده'],
        'cost not a number' => [['{provider}', '', 'EUC-0001-01', '', '', 'گران', '', '', ''], 'عدد'],
        'no cost' => [['{provider}', '', 'EUC-0001-01', '', '', '', '', '', ''], 'قیمت خرید وارد نشده'],
        'not yes or no' => [['{provider}', '', 'EUC-0001-01', '', '', 100, 'شاید', '', ''], 'بله'],
    ]);

    it('refuses the second row of the same supplier and SKU', function (): void {
        $provider = Provider::factory()->create();
        importSku('EUC-0001-01');

        $import = importFile([priceRow($provider->getId(), 'EUC-0001-01', 100), priceRow($provider->getId(), 'EUC-0001-01', 200)]);

        expect($import->rows()->whereNotNull('error')->pluck('row_number')->all())->toBe([3]);
    });

    it('fails an import whose file cannot be read', function (): void {
        Storage::disk('local')->put('imports/prices/broken.xlsx', 'not a spreadsheet');

        $import = app(StartPriceImport::class)('imports/prices/broken.xlsx', 'broken.xlsx')->fresh();

        expect($import)->getStatus()->toBe(PriceImportStatus::Failed)->getError()->toContain('خوانده نشد');
    });
});

describe('applying', function (): void {
    it('saves the changed rows, keeps blank cells and leaves rows missing from the file alone', function (): void {
        $provider = Provider::factory()->create();
        $changed = importSku('EUC-0001-01');
        $missing = importSku('EUC-0002-01');
        $new = importSku('EUC-0003-01');
        $kept = ProviderPrice::factory()->forVariant($changed->getId())->create([ProviderPrice::COLUMN_PROVIDER_ID => $provider->getId(), ProviderPrice::COLUMN_IS_ENABLED => false, ProviderPrice::COLUMN_LEAD_TIME_HOURS => 48]);
        $untouched = ProviderPrice::factory()->forVariant($missing->getId())->create([ProviderPrice::COLUMN_PROVIDER_ID => $provider->getId(), ProviderPrice::COLUMN_COST => 5_000_000]);
        $import = importFile([priceRow($provider->getId(), 'EUC-0001-01', 800_000), priceRow($provider->getId(), 'EUC-0003-01', 400_000, 'خیر')]);

        app(ConfirmPriceImport::class)($import);

        expect($import->fresh())->getStatus()->toBe(PriceImportStatus::Done)->getRowsUpdated()->toBe(2)
            ->and($kept->fresh())->getCost()->toBe(8_000_000)->isEnabled()->toBeFalse()->getLeadTimeHours()->toBe(48)
            ->and(ProviderPrice::query()->where(ProviderPrice::COLUMN_VARIANT_ID, $new->getId())->sole())->isAvailable()->toBeFalse()->isEnabled()->toBeTrue()
            ->and($untouched->fresh()->getCost())->toBe(5_000_000)
            ->and(Storage::disk('local')->exists($import->getFilePath()))->toBeFalse();
    });

    it('reprices the SKUs with the import as the reason', function (): void {
        $provider = Provider::factory()->create();
        $sku = importSku('EUC-0001-01');
        $initial = VariantPrice::factory()->create([VariantPrice::COLUMN_VARIANT_ID => $sku->getId(), VariantPrice::COLUMN_PRICE => $sku->getPrice()]);
        $sku->setCurrentPriceId($initial->getId())->save();
        $import = importFile([priceRow($provider->getId(), 'EUC-0001-01', 850_000)]);

        app(ConfirmPriceImport::class)($import);

        expect($sku->fresh()->getPrice())->toBe(9_350_000)
            ->and(VariantPrice::query()->where(VariantPrice::COLUMN_VARIANT_ID, $sku->getId())->latest('id')->first()?->getReason())->toBe(PriceChangeReason::Import);
    });

    it('applies an import only once and only with rows to change', function (): void {
        $provider = Provider::factory()->create();
        importSku('EUC-0001-01');
        $import = importFile([priceRow($provider->getId(), 'EUC-0001-01', 850_000)]);
        app(ConfirmPriceImport::class)($import);

        expect(fn () => app(ConfirmPriceImport::class)($import->fresh()))->toThrow(PriceImportRefused::class);

        $same = importFile([priceRow($provider->getId(), 'EUC-0001-01', 850_000)]);

        expect(fn () => app(ConfirmPriceImport::class)($same))->toThrow(PriceImportRefused::class, 'ردیفی برای تغییر');
    });

    it('reads and applies on the price-import queue', function (): void {
        Queue::fake();
        Storage::disk('local')->put('imports/prices/queued.xlsx', '');

        $import = app(StartPriceImport::class)('imports/prices/queued.xlsx', 'prices.xlsx');
        Queue::assertPushedOn('price-import', ParsePriceImport::class);

        $import->setStatus(PriceImportStatus::Previewed)->save();
        (new PriceImportRow)->setPriceImportId($import->getId())->setRowNumber(2)->setRaw([])->setChanges(true)->save();
        app(ConfirmPriceImport::class)($import);
        Queue::assertPushedOn('price-import', ApplyPriceImport::class);
    });
});

describe('admin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        $this->seed(RolesSeeder::class);
    });

    it('uploads a file from the suppliers list and opens its preview', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
        $provider = Provider::factory()->create();
        importSku('EUC-0001-01');
        $path = Storage::disk('local')->path('upload.xlsx');
        PriceSpreadsheet::writeTemplate($path, [priceRow($provider->getId(), 'EUC-0001-01', 850_000)]);

        livewire(ListProviders::class)
            ->callAction(TestAction::make('priceImport')->table(), ['file' => UploadedFile::fake()->createWithContent('prices.xlsx', (string) file_get_contents($path))])
            ->assertHasNoFormErrors()
            ->assertRedirect();

        expect(PriceImport::query()->sole())->getStatus()->toBe(PriceImportStatus::Previewed)->getOriginalName()->toBe('prices.xlsx');
    });

    it('applies from the preview page and offers the failed rows', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
        $provider = Provider::factory()->create();
        importSku('EUC-0001-01');
        $import = importFile([priceRow($provider->getId(), 'EUC-0001-01', 850_000), priceRow($provider->getId(), 'NOPE', 1)]);

        livewire(ViewPriceImport::class, ['record' => $import->getId()])
            ->assertActionVisible('errors')
            ->callAction('apply');

        expect($import->fresh()->getStatus())->toBe(PriceImportStatus::Done);
    });

    it('hides the import from a content editor', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');

        livewire(ViewPriceImport::class, ['record' => PriceImport::factory()->create()->getId()])->assertForbidden();
    });
});
