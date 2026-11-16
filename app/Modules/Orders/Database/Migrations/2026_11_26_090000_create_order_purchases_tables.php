<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase documents (roadmap S30, owner 2026-11-16; replaces the per-supplier provider_purchases of D3.4): one per
 * order, opened by an admin, whose rows say which supplier provided how many units of an order item at what cost. The
 * system never creates rows or costs. The order's sourcing status (admin only) follows the document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('status', 16)->default('draft')->index();
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_purchase_id')->constrained('order_purchases')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('provider_name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('missing_quantity')->default(0);
            $table->string('missing_note', 500)->nullable();
            $table->timestamps();

            $table->index('provider_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('sourcing_status', 24)->nullable()->after('payment_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['sourcing_status']);
            $table->dropColumn('sourcing_status');
        });

        Schema::dropIfExists('order_purchase_items');
        Schema::dropIfExists('order_purchases');
    }
};
