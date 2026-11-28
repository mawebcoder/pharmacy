<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Artisan command run from «فرمان‌ها» in the admin (owner, 2026-11-28): the command line, who ran it, the system
 * user it ran as, and its exit code and output.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('command', 1000);
            $table->string('status', 16);
            $table->smallInteger('exit_code')->nullable();
            $table->mediumText('output')->nullable();
            $table->string('system_user', 32)->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_runs');
    }
};
