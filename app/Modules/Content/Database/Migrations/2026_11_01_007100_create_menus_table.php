<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site menus (roadmap D3.6, S29; owner 2026-11-17): one per location with an optional heading (the footer
 * column's title), and their items in order, each leading to a picked target like a banner (link_type, link_id,
 * external_url) with an optional heroicon. MenusSeeder fills them with the site's starting links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('location', 32);
            $table->string('title', 60)->nullable();
            $table->timestamps();

            $table->unique('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
