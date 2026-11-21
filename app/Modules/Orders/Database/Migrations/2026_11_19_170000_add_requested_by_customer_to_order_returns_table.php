<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns the customer asked for from their account (roadmap S27, owner 2026-11-19), within 24 hours of delivery; they
 * reach staff as drafts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->boolean('requested_by_customer')->default(false)->after('needs_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropColumn('requested_by_customer');
        });
    }
};
