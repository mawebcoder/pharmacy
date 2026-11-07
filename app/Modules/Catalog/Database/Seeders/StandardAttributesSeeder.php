<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Enums\AttributeType;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use Illuminate\Database\Seeder;

/**
 * The standard filterable attributes (roadmap S11), based on the filters of the reference pharmacies (B1), surveyed
 * 2026-11-07: dosage form from darunet «نوع دارو» and mosbatesabz «فرم محصول»; suitable for and age from darunet
 * «مناسب برای» and mosbatesabz «رده سنی»; benefits from mosbatesabz «فیلتر کارایی». Skin and hair type are not
 * filters on those sites (only category names) and were added for our skin and hair care categories.
 * Values are cleaned up: one spelling each, Persian digits, near-duplicates merged, overly specific forms left out.
 *
 * Safe to run again: it only adds attributes and values that are missing (matched by code and by value), so
 * admins' edits, renames and new values are kept. Attributes or values an admin deleted come back only if they
 * were removed for good.
 */
final class StandardAttributesSeeder extends Seeder
{
    /**
     * code => [Persian name, type, values in display order]
     *
     * @var array<string, array{0: string, 1: AttributeType, 2: list<string>}>
     */
    public const array ATTRIBUTES = [
        'form' => ['شکل محصول', AttributeType::Select, [
            'قرص', 'قرص جوشان', 'قرص جویدنی', 'قرص مکیدنی', 'کپسول', 'سافت ژل', 'پودر', 'ساشه', 'شربت', 'سوسپانسیون',
            'قطره', 'ویال خوراکی', 'محلول', 'اسپری', 'کرم', 'ژل', 'ژل کرم', 'پماد', 'لوسیون', 'امولسیون', 'سرم',
            'روغن', 'بالم', 'استیک', 'فوم', 'شامپو', 'صابون', 'تونر', 'ماسک', 'پد', 'چسب',
        ]],
        'suitable_for' => ['مناسب برای', AttributeType::MultiSelect, [
            'بانوان', 'آقایان', 'نوزادان', 'کودکان', 'نوجوانان', 'بزرگسالان', 'سالمندان', 'باردار و شیرده', 'ورزشکاران',
        ]],
        'age_group' => ['رده سنی', AttributeType::MultiSelect, [
            'از بدو تولد', 'بالای ۱ سال', 'بالای ۳ سال', 'بالای ۶ سال', 'بالای ۱۲ سال', 'بالای ۱۸ سال', 'بالای ۳۰ سال',
            'بالای ۴۰ سال', 'بالای ۵۰ سال',
        ]],
        'benefit' => ['کاربرد', AttributeType::MultiSelect, [
            'آبرسان', 'مرطوب‌کننده', 'ضدآفتاب', 'ضد لک', 'روشن‌کننده', 'ضد چروک', 'لیفتینگ (سفت‌کننده)', 'ضد جوش و آکنه',
            'کنترل چربی', 'جمع‌کننده منافذ', 'لایه‌بردار', 'پاک‌کننده', 'ترمیم‌کننده', 'ضد قرمزی', 'ضد التهاب',
            'ضد خارش', 'ضد تیرگی دور چشم', 'ضد پف', 'ضد ترک', 'آنتی‌اکسیدان', 'تقویت مو', 'ضد ریزش مو', 'ضد شوره',
            'ضد وز مو', 'ترمیم‌کننده مو', 'تقویت ناخن', 'سلامت استخوان و مفاصل', 'تقویت سیستم ایمنی', 'انرژی‌زا',
            'تقویت سیستم عصبی',
        ]],
        'skin_type' => ['نوع پوست', AttributeType::MultiSelect, [
            'خشک', 'چرب', 'مختلط', 'معمولی', 'حساس', 'مستعد آکنه',
        ]],
        'hair_type' => ['نوع مو', AttributeType::MultiSelect, [
            'خشک', 'چرب', 'معمولی', 'رنگ‌شده', 'آسیب‌دیده', 'مستعد ریزش', 'وزدار',
        ]],
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::ATTRIBUTES as $code => [$name, $type, $values]) {
            $sort++;
            $attribute = Attribute::withTrashed()->where(Attribute::COLUMN_CODE, $code)->first();

            if (! $attribute instanceof Attribute) {
                $attribute = (new Attribute)
                    ->setCode($code)
                    ->setNameFa($name)
                    ->setType($type)
                    ->setIsFilterable(true)
                    ->setSort($sort);
                $attribute->save();
            }

            $existing = AttributeValue::query()
                ->where(AttributeValue::COLUMN_ATTRIBUTE_ID, $attribute->getId())
                ->pluck(AttributeValue::COLUMN_VALUE_FA)
                ->all();
            $next = (int) AttributeValue::query()->where(AttributeValue::COLUMN_ATTRIBUTE_ID, $attribute->getId())->max(AttributeValue::COLUMN_SORT);

            foreach ($values as $value) {
                if (in_array($value, $existing, true)) {
                    continue;
                }

                (new AttributeValue)
                    ->setAttributeId($attribute->getId())
                    ->setValueFa($value)
                    ->setSort(++$next)
                    ->save();
            }
        }
    }
}
