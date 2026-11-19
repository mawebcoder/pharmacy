<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «مایع» and «شکستنی» moved to the product (roadmap S37, owner 2026-11-19).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['is_fragile', 'is_liquid']);
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->boolean('is_fragile')->default(false);
            $table->boolean('is_liquid')->default(false);
        });
    }
};
