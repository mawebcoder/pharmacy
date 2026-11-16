<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The supplier a flash deal sells from (owner, 2026-11-17): e.g. one giving a big discount or with stock close to
 * expiry. Empty: the SKU's usual supplier. Orders under the deal copy this supplier and its cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_deals', function (Blueprint $table): void {
            $table->foreignId('provider_id')->nullable()->after('variant_id')->constrained('providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('flash_deals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provider_id');
        });
    }
};
