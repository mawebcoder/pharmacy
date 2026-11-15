<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Subcategories get their own table (owner, 2026-11-15): categories are one level, each with its subcategories
 * (/categories/{category}/{subcategory}). A product keeps its main category and may name one of its subcategories;
 * extra placements are category_product and the new product_subcategory.
 *
 * Every category below the top level becomes a subcategory of its top-level ancestor, with its products, extra
 * placements, images, banner placements and banner links. Coupon limits on it move to the top-level category; pinned
 * search shortcuts and home widgets drop it (they hold top-level categories only). Then categories lose parent_id.
 */
return new class extends Migration
{
    private const string CATEGORY_CLASS = 'App\\Modules\\Catalog\\Models\\Category';

    private const string SUBCATEGORY_CLASS = 'App\\Modules\\Catalog\\Models\\Subcategory';

    public function up(): void
    {
        Schema::create('subcategories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name_fa');
            $table->string('name_en')->nullable();
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['category_id', 'slug']);
            $table->index(['category_id', 'sort']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('primary_subcategory_id')->nullable()->after('primary_category_id')
                ->constrained('subcategories')->cascadeOnUpdate()->restrictOnDelete();
        });

        // Extra subcategories; the main one is products.primary_subcategory_id.
        Schema::create('product_subcategory', function (Blueprint $table): void {
            $table->foreignId('subcategory_id')->constrained('subcategories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->primary(['subcategory_id', 'product_id']);
            $table->index('product_id');
        });

        $this->moveLowerLevelsToSubcategories();

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id', 'sort']);
            $table->dropColumn('parent_id');
            $table->index('sort');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['sort']);
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->restrictOnDelete();
            $table->index(['parent_id', 'sort']);
        });

        foreach (DB::table('subcategories')->orderBy('id')->get() as $subcategory) {
            $slug = DB::table('categories')->where('slug', $subcategory->slug)->exists() ? $subcategory->slug.'-'.$subcategory->id : $subcategory->slug;
            $categoryId = DB::table('categories')->insertGetId([
                'parent_id' => $subcategory->category_id, 'name_fa' => $subcategory->name_fa, 'name_en' => $subcategory->name_en,
                'slug' => $slug, 'description' => $subcategory->description, 'sort' => $subcategory->sort,
                'is_active' => $subcategory->is_active, 'seo_title' => $subcategory->seo_title, 'seo_description' => $subcategory->seo_description,
                'created_at' => $subcategory->created_at, 'updated_at' => $subcategory->updated_at, 'deleted_at' => $subcategory->deleted_at,
            ]);
            DB::table('products')->where('primary_subcategory_id', $subcategory->id)->update(['primary_category_id' => $categoryId]);
            foreach (DB::table('product_subcategory')->where('subcategory_id', $subcategory->id)->pluck('product_id') as $productId) {
                DB::table('category_product')->insertOrIgnore(['category_id' => $categoryId, 'product_id' => $productId]);
            }
            DB::table('media')->where('model_type', self::SUBCATEGORY_CLASS)->where('model_id', $subcategory->id)
                ->update(['model_type' => self::CATEGORY_CLASS, 'model_id' => $categoryId]);
            DB::table('banner_placements')->where('place', 'subcategory')->where('place_id', $subcategory->id)->update(['place' => 'category', 'place_id' => $categoryId]);
            DB::table('banners')->where('link_type', 'subcategory')->where('link_id', $subcategory->id)->update(['link_type' => 'category', 'link_id' => $categoryId]);
        }

        Schema::dropIfExists('product_subcategory');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_subcategory_id');
        });
        Schema::dropIfExists('subcategories');
    }

    private function moveLowerLevelsToSubcategories(): void
    {
        $parents = DB::table('categories')->pluck('parent_id', 'id');
        $topOf = function (int $id) use ($parents): int {
            while ($parents[$id] !== null) {
                $id = (int) $parents[$id];
            }

            return $id;
        };
        $lower = DB::table('categories')->whereNotNull('parent_id')->orderBy('id')->get();
        $movedIds = [];

        foreach ($lower as $category) {
            $topId = $topOf((int) $category->id);
            $slug = (string) $category->slug;

            if (DB::table('subcategories')->where('category_id', $topId)->where('slug', $slug)->exists()) {
                $slug .= '-'.Str::lower(Str::random(4));
            }

            $subcategoryId = DB::table('subcategories')->insertGetId([
                'category_id' => $topId, 'name_fa' => $category->name_fa, 'name_en' => $category->name_en, 'slug' => $slug,
                'description' => $category->description, 'sort' => $category->sort, 'is_active' => $category->is_active,
                'seo_title' => $category->seo_title, 'seo_description' => $category->seo_description,
                'created_at' => $category->created_at, 'updated_at' => $category->updated_at, 'deleted_at' => $category->deleted_at,
            ]);

            DB::table('products')->where('primary_category_id', $category->id)
                ->update(['primary_category_id' => $topId, 'primary_subcategory_id' => $subcategoryId]);

            foreach (DB::table('category_product')->where('category_id', $category->id)->pluck('product_id') as $productId) {
                DB::table('product_subcategory')->insertOrIgnore(['subcategory_id' => $subcategoryId, 'product_id' => $productId]);
            }
            DB::table('category_product')->where('category_id', $category->id)->delete();

            DB::table('media')->where('model_type', self::CATEGORY_CLASS)->where('model_id', $category->id)
                ->update(['model_type' => self::SUBCATEGORY_CLASS, 'model_id' => $subcategoryId]);
            DB::table('banner_placements')->where('place', 'category')->where('place_id', $category->id)->update(['place' => 'subcategory', 'place_id' => $subcategoryId]);
            DB::table('banners')->where('link_type', 'category')->where('link_id', $category->id)->update(['link_type' => 'subcategory', 'link_id' => $subcategoryId]);

            foreach (DB::table('coupon_categories')->where('category_id', $category->id)->pluck('coupon_id') as $couponId) {
                DB::table('coupon_categories')->insertOrIgnore(['coupon_id' => $couponId, 'category_id' => $topId]);
            }
            DB::table('coupon_categories')->where('category_id', $category->id)->delete();

            $movedIds[] = (int) $category->id;
        }

        if ($movedIds === []) {
            return;
        }

        $this->dropIdsFromJson('home_widgets', 'settings', 'category_ids', $movedIds);
        $this->dropIdsFromSetting('search.category_ids', $movedIds);

        // Deepest first, so no row still points at a parent being deleted.
        foreach (array_reverse($movedIds) as $id) {
            DB::table('categories')->where('parent_id', $id)->update(['parent_id' => null]);
        }
        DB::table('categories')->whereIn('id', $movedIds)->delete();
    }

    /**
     * @param  list<int>  $ids
     */
    private function dropIdsFromJson(string $table, string $column, string $key, array $ids): void
    {
        foreach (DB::table($table)->whereNotNull($column)->get(['id', $column]) as $row) {
            $settings = json_decode((string) $row->{$column}, true);

            if (! is_array($settings) || ! is_array($settings[$key] ?? null)) {
                continue;
            }

            $settings[$key] = array_values(array_diff(array_map(intval(...), $settings[$key]), $ids));
            DB::table($table)->where('id', $row->id)->update([$column => json_encode($settings)]);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function dropIdsFromSetting(string $key, array $ids): void
    {
        $value = DB::table('settings')->where('key', $key)->value('value');
        $current = is_string($value) ? json_decode($value, true) : null;

        if (is_array($current)) {
            DB::table('settings')->where('key', $key)->update(['value' => json_encode(array_values(array_diff(array_map(intval(...), $current), $ids)))]);
        }
    }
};
