<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The boxes we pack in (roadmap S31 «کارتن‌ها», owner 2026-11-17): inner sizes in cm, the box's own weight and the most
 * a parcel in it may weigh (box included, at most Tapin's 6 kg), in g. Tipax's standard cartons come from
 * TipaxCartonsSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_boxes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->unsignedTinyInteger('tipax_size')->nullable();
            $table->unsignedSmallInteger('length_cm');
            $table->unsignedSmallInteger('width_cm');
            $table->unsignedSmallInteger('height_cm');
            $table->unsignedInteger('empty_weight_g');
            $table->unsignedInteger('max_weight_g');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tipax_size');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_boxes');
    }
};
