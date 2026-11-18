<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trust badges and pharmacists (roadmap D3.6, S30). A badge is either an embed code from e-Namad or Samandehi
 * (cleaned to their links and images) or an uploaded image (medialibrary collection «image») with an optional link.
 * Pharmacists have a photo (collection «photo»); one of them is the pharmacist in charge, named on /pharmacist and
 * on every product page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_badges', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 80);
            $table->text('embed_code')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_badges');
    }
};
