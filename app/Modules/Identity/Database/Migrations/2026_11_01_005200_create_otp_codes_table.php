<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront customers and their one-time sign-in codes (roadmap D3.3, D13.1, S19). Codes are stored only as a
 * keyed hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('mobile', 11);
            $table->string('code_hash', 64);
            $table->string('purpose', 16);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['code_hash', 'expires_at']);
            $table->index('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
