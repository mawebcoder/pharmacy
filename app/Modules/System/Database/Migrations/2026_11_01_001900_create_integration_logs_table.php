<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per call to an external service (roadmap D0.4, D3.6). request is redacted, then encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('service', 32);
            $table->string('operation', 64);
            $table->string('method', 8);
            $table->string('url', 2048);
            $table->text('request')->nullable();
            $table->mediumText('response')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->char('correlation_id', 36);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['service', 'operation', 'created_at']);
            $table->index('created_at');
            $table->index('correlation_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
