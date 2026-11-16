<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each order item keeps its haraj discount, coupon discount and what the customer paid, per unit and for the item
 * (owner, 2026-11-16), for returns. «discount» becomes «coupon_discount_total» and «total» becomes «paid_total»;
 * existing items are filled from the prices they already kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->renameColumn('discount', 'coupon_discount_total');
            $table->renameColumn('total', 'paid_total');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('haraj_discount_unit')->default(0)->after('unit_price');
            $table->unsignedBigInteger('haraj_discount_total')->default(0)->after('haraj_discount_unit');
            $table->unsignedBigInteger('coupon_discount_unit')->default(0)->after('haraj_discount_total');
            $table->unsignedBigInteger('paid_unit')->default(0)->after('coupon_discount_total');
        });

        DB::table('order_items')->orderBy('id')->chunkById(500, function ($items): void {
            foreach ($items as $item) {
                $quantity = max(1, (int) $item->quantity);
                $harajUnit = max(0, (int) $item->list_price - (int) $item->unit_price);
                $paidUnit = intdiv((int) $item->paid_total, $quantity);

                DB::table('order_items')->where('id', $item->id)->update([
                    'haraj_discount_unit' => $harajUnit,
                    'haraj_discount_total' => $harajUnit * $quantity,
                    'coupon_discount_unit' => (int) $item->unit_price - $paidUnit,
                    'paid_unit' => $paidUnit,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['haraj_discount_unit', 'haraj_discount_total', 'coupon_discount_unit', 'paid_unit']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->renameColumn('coupon_discount_total', 'discount');
            $table->renameColumn('paid_total', 'total');
        });
    }
};
