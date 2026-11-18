<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Seeders;

use App\Modules\Content\Models\Page;
use Illuminate\Database\Seeder;

/**
 * The eight footer pages (roadmap D3.6 «pages», S29) as unpublished drafts for the admin to write. Running it again
 * adds only pages whose address is missing, so edits and published pages are kept.
 */
final class DraftPagesSeeder extends Seeder
{
    /**
     * Address => title.
     */
    public const array PAGES = [
        'about' => 'درباره ما',
        'terms' => 'شرایط استفاده',
        'privacy' => 'حریم خصوصی',
        'returns' => 'بازگشت کالا',
        'shipping' => 'شیوه‌های ارسال',
        'payment-methods' => 'روش‌های پرداخت',
        'buying-guide' => 'راهنمای خرید',
        'licenses' => 'مجوزها',
    ];

    public function run(): void
    {
        foreach (self::PAGES as $slug => $title) {
            if (Page::withTrashed()->where(Page::COLUMN_SLUG, $slug)->exists()) {
                continue;
            }

            (new Page)->setTitle($title)->setSlug($slug)->setIsActive(false)->save();
        }
    }
}
