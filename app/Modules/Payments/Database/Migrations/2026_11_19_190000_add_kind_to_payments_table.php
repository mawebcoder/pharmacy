<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a payment row is for (owner, 2026-11-19): the order's own payment, the difference the customer pays after staff
 * edit a paid order, or a refund owed to the customer (recorded by staff with its transfer reference). Only the order's
 * own payment counts for «one paid payment per order».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('kind', 16)->default('order')->after('order_id');
            $table->dropUnique(['paid_order_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('paid_order_id');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('paid_order_id')->nullable()->after('kind')->virtualAs("IF(`status` = 'paid' AND `kind` = 'order', `order_id`, NULL)");
            $table->unique('paid_order_id');
            $table->index(['order_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'kind']);
            $table->dropUnique(['paid_order_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('paid_order_id');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('paid_order_id')->nullable()->after('order_id')->virtualAs("IF(`status` = 'paid', `order_id`, NULL)");
            $table->unique('paid_order_id');
            $table->dropColumn('kind');
        });
    }
};
