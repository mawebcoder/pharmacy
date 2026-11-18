<?php

declare(strict_types=1);

namespace App\Modules\Content\Database\Seeders;

use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Enums\MenuIcon;
use App\Modules\Content\Enums\MenuLocation;
use App\Modules\Content\Models\Menu;
use App\Modules\Content\Models\MenuItem;
use App\Modules\Content\Models\Page;
use Illuminate\Database\Seeder;

/**
 * The site menus with their starting links (roadmap S29, S30; owner 2026-11-17): the header links beside «دسته‌بندی
 * کالاها», the three footer columns and the phone and tablet menu. Page links point at DraftPagesSeeder's pages (run
 * it first); pages still in draft stay hidden until published. A menu that already exists is left as the admin made
 * it, so running it again changes nothing.
 */
final class MenusSeeder extends Seeder
{
    public function run(): void
    {
        $pages = Page::query()->pluck(Page::COLUMN_ID, Page::COLUMN_SLUG);
        $page = fn (string $title, string $slug, ?MenuIcon $icon = null): array => [$title, $icon, LinkType::Page, $pages[$slug] ?? null];
        $site = fn (string $title, LinkType $type, ?MenuIcon $icon = null): array => [$title, $icon, $type, null];

        $menus = [
            [MenuLocation::Header, null, [
                $site('همه کالاها', LinkType::AllProducts, MenuIcon::Grid),
                $site('برندها', LinkType::BrandsList, MenuIcon::Tag),
                $page('درباره ما', 'about', MenuIcon::Info),
                $site('تماس با ما', LinkType::Contact, MenuIcon::Phone),
            ]],
            [MenuLocation::Footer1, 'راهنمای خرید', [
                $page('شیوه‌های ارسال', 'shipping'),
                $page('روش‌های پرداخت', 'payment-methods'),
                $page('بازگشت کالا', 'returns'),
                $site('پرسش‌های متداول', LinkType::Faq),
            ]],
            [MenuLocation::Footer2, 'داروخانه', [
                $page('درباره ما', 'about'),
                $page('مجوزها', 'licenses'),
                $site('داروساز مسئول', LinkType::Pharmacist),
                $site('تماس با ما', LinkType::Contact),
            ]],
            [MenuLocation::Footer3, 'قوانین', [
                $page('شرایط استفاده', 'terms'),
                $page('حریم خصوصی', 'privacy'),
                $site('پیگیری سفارش', LinkType::TrackOrder),
            ]],
            [MenuLocation::Mobile, null, [
                $site('همه کالاها', LinkType::AllProducts, MenuIcon::Grid),
                $site('برندها', LinkType::BrandsList, MenuIcon::Tag),
                $site('پیشنهادهای لحظه‌ای', LinkType::Deals, MenuIcon::Bolt),
                $site('پیگیری سفارش', LinkType::TrackOrder, MenuIcon::Truck),
                $site('پرسش‌های متداول', LinkType::Faq, MenuIcon::Question),
                $page('درباره ما', 'about', MenuIcon::Info),
                $site('تماس با ما', LinkType::Contact, MenuIcon::Phone),
            ]],
        ];

        foreach ($menus as [$location, $title, $items]) {
            if (Menu::query()->where(Menu::COLUMN_LOCATION, $location)->exists()) {
                continue;
            }

            $menu = (new Menu)->setLocation($location)->setTitle($title);
            $menu->save();
            $sort = 0;

            foreach ($items as [$itemTitle, $icon, $type, $linkId]) {
                if ($type === LinkType::Page && $linkId === null) {
                    continue;
                }

                (new MenuItem)
                    ->setMenuId($menu->getId())
                    ->setTitle($itemTitle)
                    ->setIcon($icon)
                    ->setLinkType($type)
                    ->setLinkId($linkId === null ? null : (int) $linkId)
                    ->setSort(++$sort)
                    ->setIsActive(true)
                    ->save();
            }
        }
    }
}
