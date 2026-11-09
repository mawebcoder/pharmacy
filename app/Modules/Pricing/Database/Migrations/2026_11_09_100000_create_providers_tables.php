<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers, what each one charges per SKU with its change history, and the SKU price records (roadmap D3.2, S14,
 * S15). Money is integer Rial. Every existing SKU gets an «initial» record of its current prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address', 500)->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('payment_terms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('cost');
            $table->boolean('is_available')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('lead_time_hours')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'variant_id']);
            $table->index(['variant_id', 'is_enabled', 'is_available', 'cost']);
        });

        Schema::create('provider_price_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('cost')->nullable();
            $table->boolean('is_available')->nullable();
            $table->boolean('is_enabled')->nullable();
            $table->unsignedSmallInteger('lead_time_hours')->nullable();
            $table->string('event', 16);
            $table->foreignId('changed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'created_at']);
        });

        Schema::create('variant_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('official_price')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->unsignedBigInteger('provider_cost')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->string('commission_source', 16)->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->string('reason', 32);
            $table->json('flags')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'id']);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->foreignId('current_price_id')->nullable()->after('sale_price')->constrained('variant_prices')->nullOnDelete();
        });

        DB::table('product_variants')->orderBy('id')->chunkById(500, function ($variants): void {
            foreach ($variants as $variant) {
                $recordId = DB::table('variant_prices')->insertGetId([
                    'variant_id' => $variant->id,
                    'official_price' => $variant->official_price,
                    'price' => $variant->price,
                    'sale_price' => $variant->sale_price,
                    'reason' => 'initial',
                    'created_at' => now(),
                ]);

                DB::table('product_variants')->where('id', $variant->id)->update(['current_price_id' => $recordId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_price_id');
        });

        Schema::dropIfExists('variant_prices');
        Schema::dropIfExists('provider_price_changes');
        Schema::dropIfExists('provider_prices');
        Schema::dropIfExists('providers');
    }
};
