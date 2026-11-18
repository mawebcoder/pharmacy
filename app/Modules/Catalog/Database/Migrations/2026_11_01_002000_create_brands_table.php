<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product brands (roadmap D3.1). The logo is added with the image pipeline in S09.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name_fa', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('slug', 255);
            $table->char('country', 2)->nullable();
            $table->text('description')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
