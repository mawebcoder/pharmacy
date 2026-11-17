<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «داروساز مسئول» is a page from S35 (/pharmacist): it goes back into the footer's «داروخانه» column, after
 * «مجوزها», as it was before the menus became editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menuId = DB::table('menus')->where('location', 'footer_2')->value('id');

        if ($menuId === null || DB::table('menu_items')->where('menu_id', $menuId)->where('link_type', 'pharmacist')->exists()) {
            return;
        }

        $licences = DB::table('pages')->where('slug', 'licenses')->value('id');
        $after = DB::table('menu_items')->where('menu_id', $menuId)->where('link_type', 'page')->where('link_id', $licences)->value('sort');
        $sort = $after === null ? (int) DB::table('menu_items')->where('menu_id', $menuId)->max('sort') + 1 : (int) $after + 1;

        DB::table('menu_items')->where('menu_id', $menuId)->where('sort', '>=', $sort)->increment('sort');
        DB::table('menu_items')->insert([
            'menu_id' => $menuId, 'title' => 'داروساز مسئول', 'icon' => null, 'link_type' => 'pharmacist', 'link_id' => null,
            'sort' => $sort, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('link_type', 'pharmacist')->delete();
    }
};
