<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products belong to subcategories only (owner, 2026-11-15): every product gets a main subcategory, and its main
 * category is that subcategory's. A product without one goes to the first subcategory of its category, or to a new
 * «سایر» subcategory when the category has none. Then the column is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('products')->whereNull('primary_subcategory_id')->distinct()->pluck('primary_category_id') as $categoryId) {
            $subcategoryId = DB::table('subcategories')->where('category_id', $categoryId)->whereNull('deleted_at')->orderBy('sort')->orderBy('id')->value('id')
                ?? DB::table('subcategories')->insertGetId([
                    'category_id' => $categoryId,
                    'name_fa' => 'سایر',
                    'name_en' => 'Other',
                    'slug' => 'other-'.$categoryId,
                    'sort' => (int) DB::table('subcategories')->where('category_id', $categoryId)->max('sort') + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('products')->where('primary_category_id', $categoryId)->whereNull('primary_subcategory_id')->update(['primary_subcategory_id' => $subcategoryId]);
        }

        // A product's main category is its main subcategory's.
        foreach (DB::table('subcategories')->get(['id', 'category_id']) as $subcategory) {
            DB::table('products')->where('primary_subcategory_id', $subcategory->id)->update(['primary_category_id' => $subcategory->category_id]);
        }

        // MySQL does not change a column a foreign key uses, so the key is dropped and added back.
        $this->changeNullability(nullable: false);
    }

    public function down(): void
    {
        $this->changeNullability(nullable: true);
    }

    private function changeNullability(bool $nullable): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['primary_subcategory_id']);
        });
        Schema::table('products', function (Blueprint $table) use ($nullable): void {
            $table->unsignedBigInteger('primary_subcategory_id')->nullable($nullable)->change();
            $table->foreign('primary_subcategory_id')->references('id')->on('subcategories')->cascadeOnUpdate()->restrictOnDelete();
        });
    }
};
