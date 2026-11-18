<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store settings as key/value (roadmap D3.6 «settings», started in S21; S30 adds the rest). Values are JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 255);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
