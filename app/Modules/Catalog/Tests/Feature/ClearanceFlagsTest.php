<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\SyncFlashDealPrices;
use App\Modules\Catalog\Filament\Resources\Products\Pages\ListProducts;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Queries\ProductListing;
use App\Modules\Catalog\Support\ProductCardData;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Pricing\Actions\SaveProviderPrice;
use App\Modules\Pricing\Data\ProviderPriceData;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use App\Modules\Pricing\Models\VariantPrice;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

/*
| «زودمصرف» per supplier (owner, 2026-11-17): each supplier's stock of a SKU has its own expiry. The SKU is «زودمصرف»
| when the supplier the site sells from (a live flash deal's supplier first) has near-expiry stock; the badge, the
| reason strip and the expiry date on the product page follow it.
*/

beforeEach(function (): void {
    $this->product = Product::factory()->active()->create([Product::COLUMN_SLUG => 'near-expiry-cream', Product::COLUMN_COMMISSION_PERCENT => 10]);
    $this->variant = ProductVariant::factory()->for($this->product)->create([ProductVariant::COLUMN_OFFICIAL_PRICE => null]);
});

function supplierRow(ProductVariant $variant, int $cost, bool $nearExpiry = false, ?string $expiry = null, bool $preferred = false): ProviderPrice
{
    return app(SaveProviderPrice::class)(new ProviderPriceData(
        providerId: Provider::factory()->create()->getId(),
        variantId: $variant->getId(),
        cost: $cost,
        isAvailable: true,
        isEnabled: true,
        leadTimeHours: null,
        isPreferred: $preferred,
        isNearExpiry: $nearExpiry,
        expiryDate: $expiry === null ? null : CarbonImmutable::parse($expiry),
    ));
}

it('is «زودمصرف» only while the site sells from a supplier with near-expiry stock', function (): void {
    supplierRow($this->variant, 800_000, nearExpiry: true, expiry: '2027-02-10');

    expect($this->variant->fresh())->isNearExpiry()->toBeTrue()->getExpiryDate()?->toDateString()->toBe('2027-02-10')
        ->and(VariantPrice::query()->latest('id')->first())->isNearExpiry()->toBeTrue();

    // A cheaper supplier with normal stock takes over: no longer «زودمصرف».
    supplierRow($this->variant, 600_000);

    expect($this->variant->fresh())->isNearExpiry()->toBeFalse()->getExpiryDate()->toBeNull();
});

it('takes a live flash deal\'s supplier for «زودمصرف», and goes back when the deal ends', function (): void {
    supplierRow($this->variant, 600_000);
    $near = supplierRow($this->variant, 700_000, nearExpiry: true, expiry: '2027-01-05');
    $deal = FlashDeal::factory()->create([FlashDeal::COLUMN_VARIANT_ID => $this->variant->getId(), FlashDeal::COLUMN_PROVIDER_ID => $near->getProviderId(), FlashDeal::COLUMN_DEAL_PRICE => 650_000]);
    app(SyncFlashDealPrices::class)();

    expect($this->variant->fresh())->isNearExpiry()->toBeTrue()->getExpiryDate()?->toDateString()->toBe('2027-01-05');

    $deal->setIsActive(false)->save();
    app(SyncFlashDealPrices::class)();

    expect($this->variant->fresh()->isNearExpiry())->toBeFalse();
});

it('always shows «زودمصرف» on a card, with why it is discounted, even when it also has a discount', function (): void {
    $this->variant->setNearExpiry(true, CarbonImmutable::parse('2027-02-10'))->setPrice(1_000_000)->setSalePrice(850_000)->save();
    $card = ProductCardData::for($this->product->fresh());

    expect($card->badge)->toBe('زودمصرف')->and($card->nearExpiry)->toBeTrue();

    $this->blade('<x-catalog::product-card :product="$product" />', ['product' => $this->product->fresh()->load(ProductListing::CARD_RELATIONS)])
        ->assertSee(['زودمصرف', 'انقضای نزدیک؛ دلیل تخفیف']);
});

it('shows the selling supplier\'s expiry date and why it is discounted on the product page', function (): void {
    $this->product->setMinShelfLifeMonths(6)->save();
    $this->variant->setNearExpiry(true, CarbonImmutable::parse('2027-03-21'))->save();

    livewire('catalog::product-page', ['slug' => 'near-expiry-cream'])
        ->assertSeeInOrder(['زودمصرف:', 'تاریخ انقضای این کالا', '۱ فروردین ۱۴۰۶', 'است و به همین دلیل با تخفیف عرضه می‌شود.'])
        ->assertSee('تاریخ انقضا')
        ->assertDontSee('تضمین اعتبار');
});

it('filters the admin product list to products with a «زودمصرف» SKU', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
    $this->variant->setNearExpiry(true, CarbonImmutable::parse('2027-02-10'))->save();
    $sound = Product::factory()->create();
    ProductVariant::factory()->for($sound)->create();

    livewire(ListProducts::class)
        ->filterTable('near_expiry')
        ->assertCanSeeTableRecords([$this->product])
        ->assertCanNotSeeTableRecords([$sound]);
});
