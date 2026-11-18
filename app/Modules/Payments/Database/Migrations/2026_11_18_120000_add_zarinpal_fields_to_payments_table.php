<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zarinpal's own fields on a verified payment (roadmap D7.4, S36): the card's hash, who pays the fee and the fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('card_hash', 128)->nullable()->after('card_pan');
            $table->string('fee_type', 16)->nullable()->after('card_hash');
            $table->unsignedBigInteger('fee')->nullable()->after('fee_type');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['card_hash', 'fee_type', 'fee']);
        });
    }
};
