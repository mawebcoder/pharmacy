<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order may come back more than once (roadmap S27, owner 2026-11-19): each Tipax registration is told apart by our
 * trace code (the order number, «-R1», «-R2» … for returns) instead of the order and its kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipax_shipments', function (Blueprint $table): void {
            // The foreign key on order_id needs an index of its own before the old unique key goes.
            $table->index('order_id');
            $table->dropUnique(['order_id', 'kind']);
            $table->string('trace_code', 40)->nullable()->after('kind');
            $table->unique('trace_code');
        });
    }

    public function down(): void
    {
        Schema::table('tipax_shipments', function (Blueprint $table): void {
            $table->dropUnique(['trace_code']);
            $table->dropColumn('trace_code');
            $table->unique(['order_id', 'kind']);
            $table->dropIndex(['order_id']);
        });
    }
};
