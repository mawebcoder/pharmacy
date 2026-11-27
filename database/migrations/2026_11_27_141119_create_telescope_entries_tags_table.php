<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags of Telescope's entries (local debugging only).
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('telescope_entries_tags', function (Blueprint $table): void {
            $table->uuid('entry_uuid');
            $table->string('tag');

            $table->primary(['entry_uuid', 'tag']);
            $table->index('tag');

            $table->foreign('entry_uuid')->references('uuid')->on('telescope_entries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('telescope_entries_tags');
    }
};
