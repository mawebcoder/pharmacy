<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * At most one successful payment per order (owner, 2026-11-15): every attempt is kept, but only one may be paid. The
 * callback checks it under a lock; this unique key on a column filled only for paid attempts is the backstop. The
 * column is virtual: MySQL refuses a stored one on order_id, whose foreign key cascades on update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('paid_order_id')->nullable()->virtualAs("IF(`status` = 'paid', `order_id`, NULL)")->after('order_id');
            $table->unique('paid_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['paid_order_id']);
            $table->dropColumn('paid_order_id');
        });
    }
};
