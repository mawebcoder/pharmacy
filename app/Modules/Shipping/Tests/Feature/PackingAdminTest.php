<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\Shipping\Actions\SaveVariantBoxCapacities;
use App\Modules\Shipping\Database\Seeders\TipaxCartonsSeeder;
use App\Modules\Shipping\Filament\Resources\ShippingBoxes\Pages\ManageShippingBoxes;
use App\Modules\Shipping\Filament\Resources\ShippingBoxes\ShippingBoxResource;
use App\Modules\Shipping\Models\ShippingBox;
use App\Modules\Shipping\Queries\VariantBoxCapacities;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

it('seeds Tipax\'s standard cartons 1–11 once, keeping the admin\'s edits', function (): void {
    $this->seed(TipaxCartonsSeeder::class);
    ShippingBox::query()->where(ShippingBox::COLUMN_TIPAX_SIZE, 2)->update([ShippingBox::COLUMN_EMPTY_WEIGHT_G => 95]);
    $this->seed(TipaxCartonsSeeder::class);

    expect(ShippingBox::query()->orderBy(ShippingBox::COLUMN_TIPAX_SIZE)->pluck(ShippingBox::COLUMN_TIPAX_SIZE)->all())->toBe(range(1, 11))
        ->and(ShippingBox::query()->where(ShippingBox::COLUMN_TIPAX_SIZE, 2)->sole())
        ->sizesLabel()->toBe('طول ۲۰ × عرض ۱۵ × ارتفاع ۱۰ سانتی‌متر')
        ->getEmptyWeightG()->toBe(95)
        ->getMaxWeightG()->toBe(3000);
});

it('refuses a box whose maximum is above 6 kg or not above its own weight', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    $fields = [ShippingBox::COLUMN_NAME => 'کارتن', ShippingBox::COLUMN_LENGTH_CM => 20, ShippingBox::COLUMN_WIDTH_CM => 15, ShippingBox::COLUMN_HEIGHT_CM => 10, ShippingBox::COLUMN_EMPTY_WEIGHT_G => 100];

    livewire(ManageShippingBoxes::class)
        ->callAction(TestAction::make(CreateAction::class), [...$fields, ShippingBox::COLUMN_MAX_WEIGHT_G => 7000])
        ->assertHasFormErrors([ShippingBox::COLUMN_MAX_WEIGHT_G => 'max']);
    livewire(ManageShippingBoxes::class)
        ->callAction(TestAction::make(CreateAction::class), [...$fields, ShippingBox::COLUMN_MAX_WEIGHT_G => 100])
        ->assertHasFormErrors([ShippingBox::COLUMN_MAX_WEIGHT_G => 'gt']);
    livewire(ManageShippingBoxes::class)
        ->callAction(TestAction::make(CreateAction::class), [...$fields, ShippingBox::COLUMN_MAX_WEIGHT_G => 2500])
        ->assertHasNoFormErrors();

    expect(ShippingBox::query()->sole()->getMaxWeightG())->toBe(2500);
});

describe('the SKU form', function (): void {
    beforeEach(function (): void {
        $this->seed(TipaxCartonsSeeder::class);
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
        $this->product = Product::factory()->create();
        $this->small = ShippingBox::query()->where(ShippingBox::COLUMN_TIPAX_SIZE, 1)->sole()->getId();
        $this->medium = ShippingBox::query()->where(ShippingBox::COLUMN_TIPAX_SIZE, 2)->sole()->getId();
    });

    it('names each side of a carton\'s sizes on the SKU form and in the box list', function (): void {
        expect(app(VariantBoxCapacities::class)->boxOptions()[$this->small])->toEndWith('(طول ۱۵ × عرض ۱۰ × ارتفاع ۱۰ سانتی‌متر)');

        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
        livewire(ManageShippingBoxes::class)->assertSee('طول ۱۵ × عرض ۱۰ × ارتفاع ۱۰ سانتی‌متر');
    });

    it('asks how many fit at least one box', function (): void {
        livewire(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make(CreateAction::class)->table(), [ProductVariant::COLUMN_TITLE_FA => '۳۰ عددی', ProductVariant::COLUMN_WEIGHT_G => 80])
            ->assertHasFormErrors(['box_capacities.'.$this->small => 'required']);

        expect(ProductVariant::query()->count())->toBe(0);
    });

    it('saves how many fit each box, an empty box meaning it does not fit', function (): void {
        livewire(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                ProductVariant::COLUMN_TITLE_FA => '۳۰ عددی',
                ProductVariant::COLUMN_WEIGHT_G => 80,
                'box_capacities.'.$this->small => 4,
                'box_capacities.'.$this->medium => 10,
            ])
            ->assertHasNoFormErrors();

        $variant = ProductVariant::query()->sole();

        expect(app(VariantBoxCapacities::class)->for([$variant->getId()])[$variant->getId()])->toBe([$this->small => 4, $this->medium => 10]);

        livewire(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->mountAction(TestAction::make(EditAction::class)->table($variant))
            ->assertSchemaStateSet(['box_capacities.'.$this->small => 4, 'box_capacities.'.$this->medium => 10])
            ->fillForm(['box_capacities.'.$this->small => null, 'box_capacities.'.$this->medium => 10])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect(app(VariantBoxCapacities::class)->for([$variant->getId()])[$variant->getId()])->toBe([$this->medium => 10]);
    });

    it('copies the numbers from another SKU', function (): void {
        $other = ProductVariant::factory()->for(Product::factory())->create();
        app(SaveVariantBoxCapacities::class)($other->getId(), [$this->small => 2, $this->medium => 8]);

        livewire(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->mountAction(TestAction::make(CreateAction::class)->table())
            ->fillForm(['box_capacities_copy_from' => $other->getId()])
            ->assertSchemaStateSet(['box_capacities.'.$this->small => 2, 'box_capacities.'.$this->medium => 8]);
    });
});

it('keeps the packing setup from operators', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin')
        ->get(ShippingBoxResource::getUrl('index'))
        ->assertForbidden();
});
