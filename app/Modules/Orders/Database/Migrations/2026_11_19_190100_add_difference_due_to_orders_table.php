<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A paid order staff edited to a higher total (owner, 2026-11-19): the difference the customer still owes, and until
 * when its payment link is open (one hour, opened again by staff). The order does not go out while it is owed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('difference_due')->default(0)->after('total');
            $table->timestamp('difference_due_until')->nullable()->after('difference_due');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['difference_due', 'difference_due_until']);
        });
    }
};
