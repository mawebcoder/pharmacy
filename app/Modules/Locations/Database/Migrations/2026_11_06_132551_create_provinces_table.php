<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Iran's provinces (roadmap D4.2), imported from sajaddp/list-of-cities-in-Iran; source_id is the dataset id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name_fa');
            $table->string('slug')->unique();
            $table->string('tel_prefix', 8)->nullable();
            $table->unsignedBigInteger('source_id')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
