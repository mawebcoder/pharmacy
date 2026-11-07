<?php

declare(strict_types=1);

use App\Modules\Catalog\Actions\DeleteAttribute;
use App\Modules\Catalog\Actions\DeleteAttributeValue;
use App\Modules\Catalog\Actions\SaveAttribute;
use App\Modules\Catalog\Actions\SaveAttributeValue;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Database\Seeders\StandardAttributesSeeder;
use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Tag;

function productInput(Product $product, array $overrides = []): ProductData
{
    return ProductData::fromArray([
        Product::COLUMN_NAME_FA => $product->getNameFa(),
        Product::COLUMN_NAME_EN => $product->getNameEn(),
        Product::COLUMN_BRAND_ID => $product->getBrandId(),
        Product::COLUMN_PRIMARY_CATEGORY_ID => $product->getPrimaryCategoryId(),
        Product::COLUMN_PRODUCT_TYPE => $product->getProductType(),
        Product::COLUMN_STATUS => $product->getStatus(),
        ...$overrides,
    ]);
}

describe('standard attributes', function (): void {
    it('seeds the standard attributes with their values in order', function (): void {
        $this->seed(StandardAttributesSeeder::class);

        $form = Attribute::query()->where(Attribute::COLUMN_CODE, 'form')->sole();

        expect(Attribute::query()->orderBy(Attribute::COLUMN_SORT)->pluck(Attribute::COLUMN_CODE)->all())
            ->toBe(['form', 'suitable_for', 'age_group', 'benefit', 'skin_type', 'hair_type'])
            ->and($form->getType())->toBe(AttributeType::Select)
            ->and($form->values()->pluck(AttributeValue::COLUMN_VALUE_FA)->take(3)->all())->toBe(['قرص', 'قرص جوشان', 'قرص جویدنی']);
    });

    it('keeps admin changes when it runs again', function (): void {
        $this->seed(StandardAttributesSeeder::class);
        $form = Attribute::query()->where(Attribute::COLUMN_CODE, 'form')->sole();
        $form->setNameFa('فرم دارویی')->save();
        $added = app(SaveAttributeValue::class)($form, 'آدامس');
        $count = AttributeValue::query()->count();

        $this->seed(StandardAttributesSeeder::class);

        expect($form->fresh()->getNameFa())->toBe('فرم دارویی')
            ->and(AttributeValue::query()->count())->toBe($count)
            ->and($added->fresh())->not->toBeNull();
    });
});

describe('values', function (): void {
    it('adds a value at the end and refuses a duplicate', function (): void {
        $attribute = Attribute::factory()->create();
        $first = app(SaveAttributeValue::class)($attribute, 'کرم');
        $second = app(SaveAttributeValue::class)($attribute, 'ژل');

        expect($second->getSort())->toBeGreaterThan($first->getSort())
            ->and(fn () => app(SaveAttributeValue::class)($attribute, 'کرم'))->toThrow(AttributeChangeRefused::class);
    });

    it('refuses to delete a value or attribute that products use', function (): void {
        $value = AttributeValue::factory()->create();
        Product::factory()->create()->attributeValues()->attach($value);

        expect(fn () => app(DeleteAttributeValue::class)($value))->toThrow(AttributeChangeRefused::class)
            ->and(fn () => app(DeleteAttribute::class)($value->attribute))->toThrow(AttributeChangeRefused::class);

        expect($value->fresh())->not->toBeNull();
    });

    it('deletes unused values and attributes', function (): void {
        $value = AttributeValue::factory()->create();
        $attribute = $value->attribute;

        app(DeleteAttributeValue::class)($value);
        app(DeleteAttribute::class)($attribute);

        expect($value->fresh())->toBeNull()
            ->and($attribute->fresh()->trashed())->toBeTrue();
    });

    it('refuses to make an attribute single-choice while a product has several of its values', function (): void {
        $attribute = Attribute::factory()->multiple()->create();
        [$women, $children] = AttributeValue::factory()->count(2)->for($attribute)->create();
        Product::factory()->create()->attributeValues()->attach([$women->getId(), $children->getId()]);

        expect(fn () => app(SaveAttribute::class)($attribute->getNameFa(), $attribute->getCode(), AttributeType::Select, true, null, $attribute))
            ->toThrow(AttributeChangeRefused::class);
    });
});

describe('on products', function (): void {
    it('saves attribute values and tags with the product', function (): void {
        $form = Attribute::factory()->create();
        $suitableFor = Attribute::factory()->multiple()->create();
        $tablet = AttributeValue::factory()->for($form)->create();
        [$women, $pregnant] = AttributeValue::factory()->count(2)->for($suitableFor)->create();
        [$iron, $folate] = Tag::factory()->count(2)->create();
        $product = Product::factory()->create();

        $updated = app(UpdateProduct::class)($product, productInput($product, [
            'attribute_value_ids' => [$tablet->getId(), $women->getId(), $pregnant->getId()],
            'tag_ids' => [$iron->getId(), $folate->getId()],
        ]));

        expect($updated->attributeValues()->pluck('attribute_values.id')->sort()->values()->all())->toBe(collect([$tablet->getId(), $women->getId(), $pregnant->getId()])->sort()->values()->all())
            ->and($updated->tags()->pluck('tags.id')->sort()->values()->all())->toBe(collect([$iron->getId(), $folate->getId()])->sort()->values()->all());
    });

    it('refuses two values for a single-choice attribute', function (): void {
        $form = Attribute::factory()->create();
        [$tablet, $syrup] = AttributeValue::factory()->count(2)->for($form)->create();
        $product = Product::factory()->create();

        expect(fn () => app(UpdateProduct::class)($product, productInput($product, ['attribute_value_ids' => [$tablet->getId(), $syrup->getId()]])))
            ->toThrow(AttributeChangeRefused::class);

        expect($product->attributeValues()->count())->toBe(0);
    });

    it('refuses values of a deleted attribute', function (): void {
        $value = AttributeValue::factory()->create();
        $value->attribute->delete();
        $product = Product::factory()->create();

        expect(fn () => app(UpdateProduct::class)($product, productInput($product, ['attribute_value_ids' => [$value->getId()]])))
            ->toThrow(AttributeChangeRefused::class);
    });
});
