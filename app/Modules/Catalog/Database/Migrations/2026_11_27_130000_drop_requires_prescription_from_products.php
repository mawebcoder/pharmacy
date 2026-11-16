<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «نیاز به نسخه» is no longer a product switch (owner, 2026-11-16): a product needs a prescription exactly when its type
 * is «نسخه‌ای» (rx), which is not sold online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('requires_prescription');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('requires_prescription')->default(false)->after('product_type');
        });
    }
};
