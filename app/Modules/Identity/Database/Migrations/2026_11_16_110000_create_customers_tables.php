<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront customers and their one-time sign-in codes (roadmap D3.3, D13.1, S17). Codes are stored only as a
 * keyed hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('mobile', 11)->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('national_id')->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 8)->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('mobile', 11)->index();
            $table->string('code_hash', 64);
            $table->string('purpose', 16);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Uniqueness check of a new code against the live ones.
            $table->index(['code_hash', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('customers');
    }
};
