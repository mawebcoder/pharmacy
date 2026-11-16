<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\ReleaseFlashDealUnits;
use App\Modules\Catalog\Actions\ReserveFlashDealUnits;
use App\Modules\Catalog\Actions\SaveFlashDeal;
use App\Modules\Catalog\Actions\SyncFlashDealPrices;
use App\Modules\Catalog\Data\FlashDealData;
use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Catalog\Filament\Resources\FlashDeals\FlashDealResource;
use App\Modules\Catalog\Filament\Resources\FlashDeals\Pages\CreateFlashDeal;
use App\Modules\Catalog\Filament\Resources\FlashDeals\Pages\ListFlashDeals;
use App\Modules\Catalog\Jobs\SyncFlashDeals;
use App\Modules\Catalog\Models\FlashDeal;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Queries\FlashDealProducts;
use App\Modules\Catalog\Queries\FlashDealSuppliers;
use App\Modules\Catalog\Queries\PurchasableVariants;
use App\Modules\Content\Actions\SaveHomeWidget;
use App\Modules\Content\Actions\SetFlashDealWidget;
use App\Modules\Content\Data\HomeWidgetData;
use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Exceptions\HomeWidgetChangeRefused;
use App\Modules\Content\Models\HomeWidget;
use App\Modules\Content\Queries\HomeWidgetItems;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Pricing\Filament\Pages\SkuSuppliers;
use App\Modules\Pricing\Models\Provider;
use App\Modules\Pricing\Models\ProviderPrice;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    // A SKU sold at 1,000,000 rial.
    $this->product = Product::factory()->active()->create();
    $this->variant = ProductVariant::factory()->for($this->product)->create([ProductVariant::COLUMN_PRICE => 1_000_000, ProductVariant::COLUMN_OFFICIAL_PRICE => null]);
    // A supplier carrying the SKU; every deal names its supplier (owner, 2026-11-17).
    $this->supplier = Provider::factory()->create();
    ProviderPrice::factory()->create([ProviderPrice::COLUMN_PROVIDER_ID => $this->supplier->getId(), ProviderPrice::COLUMN_VARIANT_ID => $this->variant->getId(), ProviderPrice::COLUMN_COST => 700_000]);
});

function dealFor(ProductVariant $variant, array $attributes = []): FlashDeal
{
    $deal = FlashDeal::factory()->create([FlashDeal::COLUMN_VARIANT_ID => $variant->getId(), ...$attributes]);
    app(SyncFlashDealPrices::class)();

    return $deal;
}

it('sells the SKU at the deal price only inside the deal\'s window, then goes back on its own', function (): void {
    $deal = dealFor($this->variant, [FlashDeal::COLUMN_STARTS_AT => now()->addHour(), FlashDeal::COLUMN_ENDS_AT => now()->addHours(3)]);

    expect($this->variant->fresh()->currentPrice())->toBe(1_000_000);

    $this->travel(2)->hours();
    (new SyncFlashDeals)->handle(app(SyncFlashDealPrices::class));
    $variant = $this->variant->fresh();

    expect($variant->currentPrice())->toBe(800_000)
        ->and($variant->compareAtPrice())->toBe(1_000_000)
        ->and($variant->discountPercent())->toBe(20)
        ->and($variant->getFlashDealId())->toBe($deal->getId());

    $this->travel(2)->hours();
    (new SyncFlashDeals)->handle(app(SyncFlashDealPrices::class));

    expect($this->variant->fresh())->currentPrice()->toBe(1_000_000)->getFlashDealId()->toBeNull();
});

it('checks for started and ended deals every minute', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->description, SyncFlashDeals::class));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *');
});

it('refuses a deal that is not cheaper, overlaps another or drops below what sold', function (): void {
    $save = fn (array $overrides = [], ?FlashDeal $deal = null): FlashDeal => app(SaveFlashDeal::class)(FlashDealData::fromArray([
        FlashDeal::COLUMN_VARIANT_ID => $this->variant->getId(),
        FlashDeal::COLUMN_PROVIDER_ID => $this->supplier->getId(),
        FlashDeal::COLUMN_DEAL_PRICE => 800_000,
        FlashDeal::COLUMN_STARTS_AT => now()->toDateTimeString(),
        FlashDeal::COLUMN_ENDS_AT => now()->addDay()->toDateTimeString(),
        FlashDeal::COLUMN_QUANTITY_LIMIT => 10,
        ...$overrides,
    ]), $deal);

    expect(fn () => $save([FlashDeal::COLUMN_DEAL_PRICE => 1_000_000]))->toThrow(FlashDealRefused::class)
        ->and(fn () => $save([FlashDeal::COLUMN_PROVIDER_ID => null]))->toThrow(FlashDealRefused::class, 'تأمین‌کننده');

    $deal = $save();
    $deal->setSoldCount(4)->save();

    expect($this->variant->fresh()->currentPrice())->toBe(800_000)
        ->and(fn () => $save([FlashDeal::COLUMN_STARTS_AT => now()->addHours(2)->toDateTimeString()]))->toThrow(FlashDealRefused::class)
        ->and(fn () => $save([FlashDeal::COLUMN_QUANTITY_LIMIT => 3], $deal))->toThrow(FlashDealRefused::class);
});

it('never sells more units than the deal\'s limit, and units come back when released', function (): void {
    $deal = dealFor($this->variant, [FlashDeal::COLUMN_QUANTITY_LIMIT => 2]);
    $reserve = app(ReserveFlashDealUnits::class);

    $reserve($deal->getId(), 2, 'کرم');

    expect($deal->fresh()->getSoldCount())->toBe(2)
        ->and($this->variant->fresh()->currentPrice())->toBe(1_000_000)
        ->and(fn () => $reserve($deal->getId(), 1, 'کرم'))->toThrow(FlashDealRefused::class, 'کرم');

    app(ReleaseFlashDealUnits::class)($deal->getId(), 1);

    expect($deal->fresh()->getSoldCount())->toBe(1)
        ->and($this->variant->fresh()->currentPrice())->toBe(800_000);
});

it('counts down to the last live deal\'s end', function (): void {
    $other = ProductVariant::factory()->for(Product::factory()->active())->create([ProductVariant::COLUMN_PRICE => 1_000_000]);
    dealFor($this->variant, [FlashDeal::COLUMN_ENDS_AT => now()->addHours(2)]);
    dealFor($other, [FlashDeal::COLUMN_ENDS_AT => now()->addHours(5)]);
    dealFor(ProductVariant::factory()->create([ProductVariant::COLUMN_PRICE => 1_000_000]), [FlashDeal::COLUMN_ENDS_AT => now()->addDays(3), FlashDeal::COLUMN_IS_ACTIVE => false]);

    expect(app(FlashDealProducts::class)->countdownEnd()?->timestamp)->toBe(now()->addHours(5)->timestamp);
});

it('shows the flash deal widget only while a deal is live', function (): void {
    $widget = HomeWidget::factory()->ofType(HomeWidgetType::FlashDeal)->create();
    $items = app(HomeWidgetItems::class);

    expect($items->for($widget))->toBeEmpty()
        ->and($items->hiddenReason($widget))->toBe('پیشنهاد لحظه‌ای در حال اجرایی نیست');

    dealFor($this->variant);

    expect($items->for($widget)->modelKeys())->toBe([$this->product->getId()])
        ->and($items->hiddenReason($widget))->toBeNull();

    // «مشاهده همه» opens the product list filtered to live deals, with all its filters.
    $this->get(route('home'))->assertSee(route('products.index', ['deal' => 1]), false);
});

it('shows live deals, discount tiers and «زودمصرف» on /deals', function (): void {
    dealFor($this->variant);
    $nearExpiry = Product::factory()->active()->nearExpiry()->create();
    ProductVariant::factory()->for($nearExpiry)->create();

    $this->get(route('deals'))
        ->assertOk()
        ->assertSee(['تخفیف‌ها', 'پیشنهاد لحظه‌ای', $this->product->getNameFa(), '۸۰٬۰۰۰ تومان', '۲۰٪ تخفیف و بیشتر', 'زودمصرف', $nearExpiry->getNameFa()])
        ->assertSee(route('products.index', ['deal' => 1]), false)
        ->assertSee(route('products.index', ['off' => 20]), false)
        ->assertSee(route('products.index', ['expiry' => 1]), false);
});

it('sells a deal from a chosen supplier that carries the SKU', function (): void {
    $sina = Provider::factory()->create([Provider::COLUMN_NAME => 'پخش سینا']);
    $other = Provider::factory()->create();
    ProviderPrice::factory()->create([ProviderPrice::COLUMN_PROVIDER_ID => $sina->getId(), ProviderPrice::COLUMN_VARIANT_ID => $this->variant->getId(), ProviderPrice::COLUMN_COST => 600_000]);
    $data = fn (int $providerId): FlashDealData => FlashDealData::fromArray([
        FlashDeal::COLUMN_VARIANT_ID => $this->variant->getId(),
        FlashDeal::COLUMN_PROVIDER_ID => $providerId,
        FlashDeal::COLUMN_DEAL_PRICE => 800_000,
        FlashDeal::COLUMN_STARTS_AT => now()->subMinute()->toDateTimeString(),
        FlashDeal::COLUMN_ENDS_AT => now()->addDay()->toDateTimeString(),
    ]);

    expect(fn () => app(SaveFlashDeal::class)($data($other->getId())))->toThrow(FlashDealRefused::class)
        ->and(app(SaveFlashDeal::class)($data($sina->getId()))->getProviderId())->toBe($sina->getId())
        ->and(app(PurchasableVariants::class)->one($this->variant->getId())?->flashDealProviderId)->toBe($sina->getId());
});

it('puts a live deal\'s supplier first wherever the admin sees who the site sells from', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
    dealFor($this->variant, [FlashDeal::COLUMN_PROVIDER_ID => $this->supplier->getId()]);

    expect(app(FlashDealSuppliers::class)->forVariants([$this->variant->getId()]))->toBe([$this->variant->getId() => $this->supplier->getId()]);

    livewire(SkuSuppliers::class, ['variant' => $this->variant->getId()])->assertSee('فعال: پیشنهاد لحظه‌ای');
});

it('has one «پیشنهاد لحظه‌ای» home row, turned on and off from the deals list', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    livewire(ListFlashDeals::class)->callAction('homeRow');

    expect(HomeWidget::query()->sole())->getType()->toBe(HomeWidgetType::FlashDeal)->isActive()->toBeTrue()
        ->and(SetFlashDealWidget::isOn())->toBeTrue()
        ->and(fn () => app(SaveHomeWidget::class)(new HomeWidgetData(HomeWidgetType::FlashDeal, null, [], true, null, null)))->toThrow(HomeWidgetChangeRefused::class);

    livewire(ListFlashDeals::class)->callAction('homeRow');

    expect(SetFlashDealWidget::isOn())->toBeFalse()->and(HomeWidget::query()->count())->toBe(1);
});

it('shows the SKU\'s box, selling, haraj and current prices on the deal form', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
    $this->variant->setOfficialPrice(1_200_000)->setSalePrice(950_000)->save();

    livewire(CreateFlashDeal::class)
        ->fillForm([FlashDeal::COLUMN_VARIANT_ID => $this->variant->getId()])
        ->assertSee(['قیمت روی جعبه', '۱۲۰٬۰۰۰ تومان', 'قیمت فروش', '۱۰۰٬۰۰۰ تومان', 'قیمت حراج', '۹۵٬۰۰۰ تومان', 'قیمت فعلی سایت', 'قیمت فعلی این تنوع: ۹۵٬۰۰۰ تومان']);
});

it('says when the SKU\'s current price is a live deal\'s price', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
    dealFor($this->variant, [FlashDeal::COLUMN_PROVIDER_ID => $this->supplier->getId()]);

    livewire(CreateFlashDeal::class)
        ->fillForm([FlashDeal::COLUMN_VARIANT_ID => $this->variant->getId()])
        ->assertSee(['۸۰٬۰۰۰ تومان', 'با پیشنهاد لحظه‌ای فعال تا', 'قیمت عادی: ۱۰۰٬۰۰۰ تومان']);
});

it('lets a super admin set up a deal, and keeps it from operators', function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    livewire(CreateFlashDeal::class)
        ->fillForm([
            FlashDeal::COLUMN_VARIANT_ID => $this->variant->getId(),
            FlashDeal::COLUMN_PROVIDER_ID => $this->supplier->getId(),
            FlashDeal::COLUMN_DEAL_PRICE => '85,000',
            FlashDeal::COLUMN_STARTS_AT => now()->subMinute()->toDateTimeString(),
            FlashDeal::COLUMN_ENDS_AT => now()->addDay()->toDateTimeString(),
            FlashDeal::COLUMN_QUANTITY_LIMIT => 5,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FlashDeal::query()->sole())->getDealPrice()->toBe(850_000)->getQuantityLimit()->toBe(5)->getProviderId()->toBe($this->supplier->getId())
        ->and($this->variant->fresh()->currentPrice())->toBe(850_000);

    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    $this->get(FlashDealResource::getUrl('index'))->assertForbidden();
});
