<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The questions of /faq (roadmap D3.6, S29): a group from a fixed list (FaqGroup), the question, a plain-text answer,
 * the order within the group and an on/off switch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 32);
            $table->string('question', 255);
            $table->text('answer');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'group', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
