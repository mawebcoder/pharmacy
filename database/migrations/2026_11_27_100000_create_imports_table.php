<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filament's import action (catalog import, owner 2026-11-27): each uploaded CSV and its progress. The admin is a
 * polymorphic user (admin_users), see AppServiceProvider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('importer');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows')->default(0);
            $table->morphs('user');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
