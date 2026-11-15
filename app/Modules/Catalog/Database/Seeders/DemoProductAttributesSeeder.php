<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Search\ProductSearchIndex;
use Illuminate\Database\Seeder;

/**
 * Development only, not in DatabaseSeeder: gives every active product sample values of the supplement filters added
 * from Provin (owner, 2026-11-15), so the list pages show those filter groups with counts. Seeds the standard
 * attributes first. The same product always gets the same values; values a product already has are kept, and a
 * single-choice attribute it already has is left alone. Products are sent to search again afterwards.
 * php artisan db:seed --class="App\Modules\Catalog\Database\Seeders\DemoProductAttributesSeeder"
 */
final class DemoProductAttributesSeeder extends Seeder
{
    /**
     * Codes of the attributes that get sample values.
     */
    public const array CODES = ['form', 'food_context', 'usage_frequency', 'diet', 'allergens', 'doses_per_day', 'dose_time', 'dose_unit'];

    public function run(): void
    {
        $this->call(StandardAttributesSeeder::class);

        $attributes = Attribute::query()->whereIn(Attribute::COLUMN_CODE, self::CODES)->with('values')->get();

        Product::query()->active()->with('attributeValues')->each(function (Product $product) use ($attributes): void {
            $has = $product->getAttributeValues()->pluck(AttributeValue::COLUMN_ATTRIBUTE_ID)->all();
            $ids = [];

            foreach ($attributes as $attribute) {
                $values = $attribute->getValues()->map(fn (AttributeValue $value): int => $value->getId())->values()->all();
                $single = $attribute->getType() === AttributeType::Select;

                if ($values === [] || ($single && in_array($attribute->getId(), $has, true))) {
                    continue;
                }

                // A spread that depends only on the product and the attribute, so reruns pick the same values.
                $start = crc32($product->getId().':'.$attribute->getCode()) % count($values);

                foreach (range(0, $single ? 0 : min(1, count($values) - 1)) as $step) {
                    $ids[] = $values[($start + $step * 3) % count($values)];
                }
            }

            $product->attributeValues()->syncWithoutDetaching($ids);
            ProductSearchIndex::refresh($product->unsetRelation('attributeValues'));
        });
    }
}
