<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses in parts (owner, 2026-11-14, S25): main street (required in the form), side street, alley and block,
 * so the address text is generated the same way everywhere (AddressText). The free «نشانی» becomes optional extra
 * directions; addresses saved before keep their text there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('main_street', 120)->nullable()->after('city_id');
            $table->string('side_street', 120)->nullable()->after('main_street');
            $table->string('alley', 120)->nullable()->after('side_street');
            $table->string('block', 20)->nullable()->after('alley');
            $table->string('full_address', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropColumn(['main_street', 'side_street', 'alley', 'block']);
        });
    }
};
