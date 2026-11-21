<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund records (owner, 2026-11-19): where a refund came from (an order edit, a cancelled order or a return, with the
 * return's id), why, its items/shipping split for a cancelled order,
 * who created it, and who approved or rejected it and when. Approving records the bank transfer reference (ref_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('refund_source', 16)->nullable()->after('kind');
            $table->unsignedBigInteger('order_return_id')->nullable()->after('refund_source');
            $table->string('reason', 500)->nullable()->after('failure_reason');
            // A cancelled order's refund split into items and shipping (rial), so staff can lower either on approval.
            $table->json('details')->nullable()->after('reason');
            $table->unsignedBigInteger('created_by')->nullable()->after('reason');
            $table->unsignedBigInteger('decided_by')->nullable()->after('created_by');
            $table->timestamp('decided_at')->nullable()->after('decided_by');

            $table->unique('order_return_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['order_return_id']);
            $table->dropColumn(['refund_source', 'order_return_id', 'reason', 'details', 'created_by', 'decided_by', 'decided_at']);
        });
    }
};
