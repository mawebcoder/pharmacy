<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages sent from /contact (roadmap D3.6, S34): who sent it (the signed-in customer too, when there is one), the
 * subject and text, where staff are with it (new, read, answered) and the sender's IP for abuse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('name', 80);
            $table->string('mobile', 11);
            $table->string('email', 120)->nullable();
            $table->string('subject', 32);
            $table->text('body');
            $table->string('status', 16)->default('new');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
