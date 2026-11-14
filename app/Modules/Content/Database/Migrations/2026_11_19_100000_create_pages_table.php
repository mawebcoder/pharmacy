<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Static pages (roadmap D3.6, S34): title, URL slug (/pages/{slug}), rich-editor HTML body and SEO fields. The eight
 * pages the footer links to are created as inactive drafts for the admin to write (owner, 2026-11-14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('slug', 120)->unique();
            $table->longText('body')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('seo_title', 120)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $drafts = [
            'about' => 'درباره ما',
            'terms' => 'شرایط استفاده',
            'privacy' => 'حریم خصوصی',
            'returns' => 'بازگشت کالا',
            'shipping' => 'شیوه‌های ارسال',
            'payment-methods' => 'روش‌های پرداخت',
            'buying-guide' => 'راهنمای خرید',
            'licenses' => 'مجوزها',
        ];

        DB::table('pages')->insert(array_map(
            fn (string $slug, string $title): array => ['title' => $title, 'slug' => $slug, 'is_active' => false, 'created_at' => $now, 'updated_at' => $now],
            array_keys($drafts),
            $drafts,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
