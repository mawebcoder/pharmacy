<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Support\CatalogSlug;
use Illuminate\Database\Seeder;

/**
 * The store's starting categories and their subcategories (owner, 2026-11-15: two levels, subcategories in their own
 * table). Safe to run again: it only adds what is missing, matching categories and subcategories by Persian name, so
 * admins' renames, new rows and order are kept; a category an admin already made (e.g. «مراقبت پوست») just gets
 * the missing subcategories.
 */
final class CategoriesSeeder extends Seeder
{
    /**
     * Persian name => [English name, [Persian subcategory name => English name, …]], in menu order.
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    public const array TREE = [
        'مکمل‌ها' => ['Supplements', [
            'ویتامین‌ها' => 'Vitamins',
            'مواد معدنی' => 'Minerals',
            'امگا ۳ و روغن ماهی' => 'Omega 3 and Fish Oil',
            'پروبیوتیک‌ها' => 'Probiotics',
            'مکمل‌های گیاهی' => 'Herbal Supplements',
        ]],
        'مراقبت پوست' => ['Skin Care', [
            'پاک‌کننده‌ها' => 'Cleansers',
            'مرطوب‌کننده‌ها' => 'Moisturizers',
            'ضدآفتاب‌ها' => 'Sunscreens',
            'سرم‌ها' => 'Serums',
            'ضد جوش و آکنه' => 'Acne Care',
        ]],
        'مراقبت مو' => ['Hair Care', [
            'شامپوها' => 'Shampoos',
            'نرم‌کننده‌ها' => 'Conditioners',
            'ضد ریزش مو' => 'Hair Loss',
            'ماسک مو' => 'Hair Masks',
        ]],
        'مادر و کودک' => ['Mother and Baby', [
            'شیر خشک' => 'Baby Formula',
            'پوشک' => 'Diapers',
            'مراقبت پوست کودک' => 'Baby Skin Care',
            'دوران بارداری' => 'Pregnancy',
        ]],
        'بهداشت دهان و دندان' => ['Oral Care', [
            'خمیردندان' => 'Toothpaste',
            'مسواک' => 'Toothbrushes',
            'دهان‌شویه' => 'Mouthwash',
        ]],
        'مکمل‌های ورزشی' => ['Sports Nutrition', [
            'پروتئین' => 'Protein',
            'کراتین' => 'Creatine',
            'آمینو اسیدها' => 'Amino Acids',
            'الکترولیت‌ها' => 'Electrolytes',
        ]],
        'تجهیزات پزشکی' => ['Medical Devices', [
            'فشارسنج' => 'Blood Pressure Monitors',
            'تست قند خون' => 'Glucose Meters',
            'دماسنج' => 'Thermometers',
        ]],
        'آرایشی' => ['Cosmetics', [
            'آرایش صورت' => 'Face Makeup',
            'آرایش چشم' => 'Eye Makeup',
            'مراقبت و رژ لب' => 'Lip Care',
        ]],
    ];

    public function run(): void
    {
        $sort = (int) Category::withTrashed()->max(Category::COLUMN_SORT);

        foreach (self::TREE as $nameFa => [$nameEn, $subcategories]) {
            $category = Category::withTrashed()->where(Category::COLUMN_NAME_FA, $nameFa)->first();

            if (! $category instanceof Category) {
                $category = (new Category)
                    ->setNameFa($nameFa)
                    ->setNameEn($nameEn)
                    ->setSlug(CatalogSlug::for(Category::class, null, $nameFa, $nameEn))
                    ->setSort(++$sort)
                    ->setIsActive(true);
                $category->save();
            }

            $existing = Subcategory::withTrashed()->where(Subcategory::COLUMN_CATEGORY_ID, $category->getId())->pluck(Subcategory::COLUMN_NAME_FA)->all();
            $next = (int) Subcategory::withTrashed()->where(Subcategory::COLUMN_CATEGORY_ID, $category->getId())->max(Subcategory::COLUMN_SORT);

            foreach ($subcategories as $subcategoryFa => $subcategoryEn) {
                if (in_array($subcategoryFa, $existing, true)) {
                    continue;
                }

                (new Subcategory)
                    ->setCategoryId($category->getId())
                    ->setNameFa($subcategoryFa)
                    ->setNameEn($subcategoryEn)
                    ->setSlug(CatalogSlug::for(Subcategory::class, null, $subcategoryFa, $subcategoryEn))
                    ->setSort(++$next)
                    ->setIsActive(true)
                    ->save();
            }
        }
    }
}
