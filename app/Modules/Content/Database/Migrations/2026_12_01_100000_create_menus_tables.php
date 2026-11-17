<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The site menus (roadmap D3.6, S34; owner 2026-11-17): one per location with an optional heading (the footer
 * column's title), and their items in order, each leading to a picked target like a banner (link_type, link_id,
 * external_url) with an optional heroicon. Filled with the links the header and footer had until now, so nothing
 * changes on the first deploy; pages that are still drafts stay hidden until published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('location', 32)->unique();
            $table->string('title', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('title', 60);
            $table->string('icon', 40)->nullable();
            $table->string('link_type', 32);
            $table->unsignedBigInteger('link_id')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'is_active', 'sort']);
        });

        $now = now();
        $pages = DB::table('pages')->pluck('id', 'slug');
        $page = fn (string $title, string $slug, ?string $icon = null): array => ['title' => $title, 'icon' => $icon, 'link_type' => 'page', 'link_id' => $pages[$slug] ?? null];
        $site = fn (string $title, string $type, ?string $icon = null): array => ['title' => $title, 'icon' => $icon, 'link_type' => $type, 'link_id' => null];

        $menus = [
            'header' => [null, [
                $site('همه کالاها', 'products', 'squares-2x2'),
                $site('برندها', 'brands_list', 'tag'),
                $page('درباره ما', 'about', 'information-circle'),
                $site('تماس با ما', 'contact', 'phone'),
            ]],
            'footer_1' => ['راهنمای خرید', [
                $page('شیوه‌های ارسال', 'shipping'),
                $page('روش‌های پرداخت', 'payment-methods'),
                $page('بازگشت کالا', 'returns'),
                $site('پرسش‌های متداول', 'faq'),
            ]],
            'footer_2' => ['داروخانه', [
                $page('درباره ما', 'about'),
                $page('مجوزها', 'licenses'),
                $site('تماس با ما', 'contact'),
            ]],
            'footer_3' => ['قوانین', [
                $page('شرایط استفاده', 'terms'),
                $page('حریم خصوصی', 'privacy'),
                $site('پیگیری سفارش', 'track'),
            ]],
            'mobile' => [null, [
                $site('همه کالاها', 'products', 'squares-2x2'),
                $site('برندها', 'brands_list', 'tag'),
                $site('پیشنهادهای لحظه‌ای', 'deals', 'bolt'),
                $site('پیگیری سفارش', 'track', 'truck'),
                $site('پرسش‌های متداول', 'faq', 'question-mark-circle'),
                $page('درباره ما', 'about', 'information-circle'),
                $site('تماس با ما', 'contact', 'phone'),
            ]],
        ];

        foreach ($menus as $location => [$title, $items]) {
            $menuId = DB::table('menus')->insertGetId(['location' => $location, 'title' => $title, 'created_at' => $now, 'updated_at' => $now]);

            foreach (array_values(array_filter($items, fn (array $item): bool => $item['link_type'] !== 'page' || $item['link_id'] !== null)) as $sort => $item) {
                DB::table('menu_items')->insert([...$item, 'menu_id' => $menuId, 'sort' => $sort + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
