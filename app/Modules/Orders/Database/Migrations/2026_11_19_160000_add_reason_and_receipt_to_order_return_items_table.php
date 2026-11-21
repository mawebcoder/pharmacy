<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Return rows (roadmap S27, owner 2026-11-19): each row has its own reason (from a fixed list, with a note) and the
 * units confirmed received at the warehouse. One order item may be on several rows with different reasons, so the
 * unique key goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_return_items', function (Blueprint $table): void {
            // The foreign key on order_return_id needs an index of its own before the unique key goes.
            $table->index('order_return_id');
            $table->dropUnique(['order_return_id', 'order_item_id']);
            $table->string('reason', 32)->default('other')->after('quantity');
            $table->string('reason_note', 500)->nullable()->after('reason');
            $table->unsignedInteger('received_quantity')->nullable()->after('reason_note');
        });
    }

    public function down(): void
    {
        Schema::table('order_return_items', function (Blueprint $table): void {
            $table->dropColumn(['reason', 'reason_note', 'received_quantity']);
            $table->unique(['order_return_id', 'order_item_id']);
            $table->dropIndex(['order_return_id']);
        });
    }
};
