<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags Telescope always records, set from its dashboard (local debugging only).
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('telescope_monitoring', function (Blueprint $table): void {
            $table->string('tag')->primary();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('telescope_monitoring');
    }
};
