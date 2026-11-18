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
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('mobile', 11);
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();
            $table->text('national_id')->nullable();
            $table->string('email', 255)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 8)->nullable();
            $table->string('theme', 10)->default('system');
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();

            $table->unique('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
