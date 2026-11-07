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
            $table->string('term')->unique();
            $table->json('synonyms');
            $table->timestamps();
        });

        Schema::create('search_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('query', 255);
            $table->string('normalized_query', 255);
            $table->unsignedInteger('results_count');
            // Customers arrive in S17; the foreign key is added then.
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('clicked_product_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['normalized_query', 'created_at']);
            $table->index(['results_count', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('search_synonyms');
    }
};
