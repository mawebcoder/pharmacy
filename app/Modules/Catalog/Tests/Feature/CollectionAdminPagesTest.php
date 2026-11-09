<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Collections\Pages\EditCollection;
use App\Modules\Catalog\Filament\Resources\Collections\RelationManagers\CollectionProductsRelationManager;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCollection;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->seed(RolesSeeder::class);
});

describe('as a content editor', function (): void {
    beforeEach(function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin');
    });

    it('adds the products of the picked SKUs once each', function (): void {
        $collection = ProductCollection::factory()->create();
        $product = Product::factory()->create();
        [$small, $large] = ProductVariant::factory()->for($product)->count(2)->create();

        livewire(CollectionProductsRelationManager::class, ['ownerRecord' => $collection, 'pageClass' => EditCollection::class])
            ->callAction(TestAction::make('addProducts')->table(), ['sku_ids' => [$small->getId(), $large->getId()]])
            ->assertHasNoFormErrors();

        expect($collection->products()->pluck('products.id')->all())->toBe([$product->getId()]);
    });

    it('cannot delete «محصولات محبوب»', function (): void {
        $popular = ProductCollection::query()->where(ProductCollection::COLUMN_SLUG, ProductCollection::POPULAR_SLUG)->sole();

        livewire(EditCollection::class, ['record' => $popular->getRouteKey()])->assertActionHidden(DeleteAction::class);
    });
});

it('finds SKUs by product name and SKU code', function (string $term): void {
    $product = Product::factory()->create([Product::COLUMN_NAME_FA => 'کرم مرطوب‌کننده اوره']);
    $sku = ProductVariant::factory()->for($product)->create([ProductVariant::COLUMN_SKU => 'EUC-000101-01']);
    ProductVariant::factory()->create();

    expect(array_keys(app(AdminOptions::class)->search('sku', $term)))->toBe([$sku->getId()]);
})->with(['product name' => 'مرطوب', 'SKU code' => 'EUC-0001']);

it('finds a SKU by its id and by its product id', function (): void {
    $sku = ProductVariant::factory()->create();

    expect(app(AdminOptions::class)->search('sku', (string) $sku->getId()))->toHaveKey($sku->getId())
        ->and(app(AdminOptions::class)->search('sku', (string) $sku->getProductId()))->toHaveKey($sku->getId());
});
