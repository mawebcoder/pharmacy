<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Tipax content a packed parcel's products share (owner, 2026-11-19), kept so registering it with Tipax (S35) sends
 * what checkout priced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_parcels', function (Blueprint $table): void {
            $table->unsignedInteger('tipax_content_id')->nullable()->after('content_type');
        });
    }

    public function down(): void
    {
        Schema::table('order_parcels', function (Blueprint $table): void {
            $table->dropColumn('tipax_content_id');
        });
    }
};
