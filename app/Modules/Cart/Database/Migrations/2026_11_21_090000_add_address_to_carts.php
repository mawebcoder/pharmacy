<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The address the customer picked on the checkout address step (roadmap D3.4, S25); empty means «use the default».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('address_id')->nullable()->after('session_token_hash')->constrained('addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('carts', 'address_id')) {
            Schema::table('carts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('address_id');
            });
        }
    }
};
