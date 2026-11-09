<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Schemas;

use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Media\Filament\Forms\ImageUpload;
use App\Modules\Shared\Support\PersianDigits;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Field rules come from StoreCategoryRequest::baseRules() so admin and HTTP validation never drift (roadmap C5).
 */
final class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = fn (?Category $record, string $field): array => StoreCategoryRequest::baseRules($record?->getId())[$field];

        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Category::COLUMN_NAME_FA)
                            ->label('نام فارسی')
                            ->required()
                            ->maxLength(255)
                            ->rules(fn (?Category $record): array => $rules($record, Category::COLUMN_NAME_FA)),
                        TextInput::make(Category::COLUMN_NAME_EN)
                            ->label('نام انگلیسی')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->maxLength(255)
                            ->rules(fn (?Category $record): array => $rules($record, Category::COLUMN_NAME_EN)),
                        ImageSelect::images(Select::make(Category::COLUMN_PARENT_ID), 'category', fn (?Category $record = null): array => self::parentOptions($record))
                            ->label('دسته والد')
                            ->placeholder('سطح اول (بدون والد)')
                            ->rules(fn (?Category $record): array => $rules($record, Category::COLUMN_PARENT_ID))
                            ->helperText('حداکثر '.PersianDigits::toPersian(Category::MAX_DEPTH).' سطح؛ زیردسته‌ها همراه دسته جابه‌جا می‌شوند.'),
                        TextInput::make(Category::COLUMN_SLUG)
                            ->label('نامک (آدرس)')
                            ->prefix('/categories/')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->maxLength(255)
                            ->rules(fn (?Category $record): array => $rules($record, Category::COLUMN_SLUG))
                            ->helperText(fn (?Category $record): string => $record instanceof Category
                                ? 'تغییر نامک، پیوندهای قبلی این دسته را از کار می‌اندازد.'
                                : 'خالی بگذارید تا از نام انگلیسی (یا فارسی) ساخته شود.'),
                        Toggle::make(Category::COLUMN_IS_ACTIVE)
                            ->label('فعال')
                            ->helperText('دسته غیرفعال و زیردسته‌هایش در سایت نمایش داده نمی‌شوند.')
                            ->default(true),
                    ]),
                Section::make('معرفی')
                    ->schema([
                        Textarea::make(Category::COLUMN_DESCRIPTION)
                            ->label('توضیحات')
                            ->rows(5)
                            ->maxLength(5000)
                            ->rules(fn (?Category $record): array => $rules($record, Category::COLUMN_DESCRIPTION)),
                    ]),
                Section::make('تصاویر')
                    ->columns(['md' => 3])
                    ->schema([
                        ImageUpload::make(Category::IMAGE_ICON, 'category_icon')
                            ->label('آیکن'),
                        ImageUpload::make(Category::IMAGE_BANNER_DESKTOP, 'category_banner_desktop')
                            ->label('بنر دسکتاپ و تبلت'),
                        ImageUpload::make(Category::IMAGE_BANNER_MOBILE, 'category_banner_mobile')
                            ->label('بنر موبایل'),
                    ]),
                Section::make('سئو')
                    ->description('خالی بگذارید تا از نام و توضیحات دسته استفاده شود.')
                    ->collapsible()
                    ->schema([
                        TextInput::make(Category::COLUMN_SEO_TITLE)
                            ->label('عنوان صفحه')
                            ->maxLength(255)
                            ->rules(fn (?Category $record): array => $rules($record, Category::COLUMN_SEO_TITLE)),
                        Textarea::make(Category::COLUMN_SEO_DESCRIPTION)
                            ->label('توضیحات متا')
                            ->rows(3)
                            ->maxLength(500)
                            ->rules(fn (?Category $record): array => $rules($record, Category::COLUMN_SEO_DESCRIPTION)),
                    ]),
            ]);
    }

    /**
     * Categories that can hold children (not on the last level), shown as paths ("مکمل‌ها › ویتامین‌ها").
     * The category being edited and its subcategories are left out.
     *
     * @return array<int, string>
     */
    private static function parentOptions(?Category $record): array
    {
        $categories = Category::query()->ordered()->get([Category::COLUMN_ID, Category::COLUMN_PARENT_ID, Category::COLUMN_NAME_FA])->keyBy(Category::COLUMN_ID);
        $options = [];

        foreach ($categories as $category) {
            $path = [$category->getNameFa()];
            $parentId = $category->getParentId();
            $excluded = $record instanceof Category && $category->getId() === $record->getId();

            while ($parentId !== null && $categories->has($parentId) && count($path) <= Category::MAX_DEPTH) {
                $excluded = $excluded || ($record instanceof Category && $parentId === $record->getId());
                /** @var Category $parent */
                $parent = $categories->get($parentId);
                array_unshift($path, $parent->getNameFa());
                $parentId = $parent->getParentId();
            }

            if (! $excluded && count($path) < Category::MAX_DEPTH) {
                $options[$category->getId()] = implode(' › ', $path);
            }
        }

        asort($options);

        return $options;
    }
}
