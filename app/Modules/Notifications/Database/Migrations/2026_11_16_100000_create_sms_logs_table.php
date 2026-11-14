<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every SMS sent or tried (roadmap D11.6): provider, template, masked mobile, variables (null for secret templates
 * such as sign-in codes), whether it was a pretend send, the provider's message id, result code and duration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24);
            $table->string('template', 32);
            $table->string('recipient', 16);
            $table->json('variables')->nullable();
            $table->string('pattern_id', 32)->nullable();
            $table->boolean('is_pretend');
            $table->boolean('success');
            $table->string('provider_message_id', 64)->nullable();
            $table->string('code', 32);
            $table->string('error')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['template', 'created_at']);
            $table->index(['success', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
