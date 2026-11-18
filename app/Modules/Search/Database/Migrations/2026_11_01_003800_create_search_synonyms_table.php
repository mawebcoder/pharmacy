<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search synonyms (copied into Meilisearch) and search logs for the insights page (roadmap D3.6, D21.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_synonyms', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 255);
            $table->json('synonyms');
            $table->timestamps();

            $table->unique('term');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
