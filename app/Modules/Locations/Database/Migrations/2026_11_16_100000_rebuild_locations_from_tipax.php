<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Locations follow Tipax (decided 2026-11-14 in S18): provinces and cities come from Tipax's Cities/plusstate, and
 * their ids are Tipax's (stateId, city id), so addresses and shipments use them with no mapping. Counties, regions and
 * neighbourhoods (from the earlier open dataset) are dropped. Run `locations:import-tipax` afterwards.
 * Providers keep their rows; their city is cleared because the old city ids no longer exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array('providers_city_id_foreign', array_column(Schema::getForeignKeys('providers'), 'name'), true)) {
            Schema::table('providers', function (Blueprint $table): void {
                $table->dropForeign(['city_id']);
            });
        }
        DB::table('providers')->update(['city_id' => null]);

        Schema::dropIfExists('neighbourhoods');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('counties');
        Schema::dropIfExists('provinces');

        Schema::create('provinces', function (Blueprint $table): void {
            // Tipax's stateId, not auto-increment.
            $table->unsignedBigInteger('id')->primary();
            $table->string('name_fa');
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            // Tipax's city id, not auto-increment.
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('province_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name_fa');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('has_tipax_branch')->default(false);
            $table->boolean('is_delivery_enabled')->default(false);
            $table->timestamp('tipax_synced_at')->nullable();
            $table->timestamps();

            $table->index('is_delivery_enabled');
            $table->index(['province_id', 'name_fa']);
        });

        Schema::table('providers', function (Blueprint $table): void {
            $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
        });
    }

    public function down(): never
    {
        throw new RuntimeException('The open-dataset location tables are not restored; re-run the S05 migrations from a fresh database.');
    }
};
