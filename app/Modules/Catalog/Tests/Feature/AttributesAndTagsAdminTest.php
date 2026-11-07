<?php

declare(strict_types=1);

use App\Modules\Catalog\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Modules\Catalog\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Modules\Catalog\Filament\Resources\Attributes\RelationManagers\ValuesRelationManager;
use App\Modules\Catalog\Filament\Resources\Products\Pages\EditProduct;
use App\Modules\Catalog\Filament\Resources\Tags\Pages\CreateTag;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use Filament\Actions\CreateAction;
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

    it('creates an attribute and adds its values', function (): void {
        livewire(CreateAttribute::class)
            ->fillForm([Attribute::COLUMN_NAME_FA => 'طعم', Attribute::COLUMN_CODE => 'flavour', Attribute::COLUMN_TYPE => 'select', Attribute::COLUMN_IS_FILTERABLE => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $attribute = Attribute::query()->where(Attribute::COLUMN_CODE, 'flavour')->sole();

        livewire(ValuesRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->callAction(TestAction::make(CreateAction::class)->table(), [AttributeValue::COLUMN_VALUE_FA => 'پرتقال'])
            ->assertHasNoFormErrors();

        expect($attribute->values()->pluck(AttributeValue::COLUMN_VALUE_FA)->all())->toBe(['پرتقال']);
    });

    it('rejects an attribute code that is not a lowercase Latin name', function (): void {
        livewire(CreateAttribute::class)
            ->fillForm([Attribute::COLUMN_NAME_FA => 'طعم', Attribute::COLUMN_CODE => 'طعم', Attribute::COLUMN_TYPE => 'select'])
            ->call('create')
            ->assertHasFormErrors([Attribute::COLUMN_CODE]);
    });

    it('creates a featured tag', function (): void {
        livewire(CreateTag::class)
            ->fillForm([Tag::COLUMN_NAME_FA => 'آهن', Tag::COLUMN_NAME_EN => 'Iron', Tag::COLUMN_TYPE => 'nutrient', Tag::COLUMN_IS_FEATURED => true, Tag::COLUMN_SORT => 1])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Tag::query()->sole())->getSlug()->toBe('iron')->isFeatured()->toBeTrue();
    });

    it('sets attribute values and tags from the product form', function (): void {
        $form = Attribute::factory()->create([Attribute::COLUMN_CODE => 'form']);
        $suitableFor = Attribute::factory()->multiple()->create([Attribute::COLUMN_CODE => 'suitable_for']);
        $tablet = AttributeValue::factory()->for($form)->create();
        [$women, $pregnant] = AttributeValue::factory()->count(2)->for($suitableFor)->create();
        $iron = Tag::factory()->create();
        $product = Product::factory()->create();

        livewire(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([
                'attribute_values' => ['form' => $tablet->getId(), 'suitable_for' => [$women->getId(), $pregnant->getId()]],
                'tag_ids' => [$iron->getId()],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($product->attributeValues()->count())->toBe(3)
            ->and($product->tags()->pluck('tags.id')->all())->toBe([$iron->getId()]);

        livewire(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['attribute_values.form' => $tablet->getId(), 'tag_ids' => [$iron->getId()]]);
    });

    it('cannot delete attributes', function (): void {
        $attribute = Attribute::factory()->create();

        livewire(EditAttribute::class, ['record' => $attribute->getRouteKey()])->assertActionHidden(DeleteAction::class);
    });
});

it('keeps operators out of attributes and tags', function (string $url): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin')
        ->get($url)
        ->assertForbidden();
})->with(['/admin/attributes', '/admin/tags']);
