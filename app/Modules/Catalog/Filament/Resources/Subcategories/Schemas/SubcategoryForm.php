<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Subcategories\Schemas;

use App\Modules\Catalog\Filament\Forms\DescriptionEditor;
use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Http\Requests\StoreSubcategoryRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Media\Filament\Forms\ImageUpload;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The subcategory form, in a category's «زیردسته‌ها» tab and on the «زیردسته‌ها» page. Only the page picks the main
 * category, so a subcategory moves there (SaveSubcategory). Field rules come from StoreSubcategoryRequest::baseRules().
 */
final class SubcategoryForm
{
    /**
     * @param  Closure(Get): ?string|null  $categorySlug  the slug shown before its own; null shows the category field
     */
    public static function configure(Schema $schema, ?Closure $categorySlug = null): Schema
    {
        $rules = fn (?Subcategory $record, string $field): array => StoreSubcategoryRequest::baseRules($record?->getId())[$field] ?? [];
        $picksCategory = $categorySlug === null;
        $categorySlug ??= fn (Get $get): ?string => Category::query()->whereKey((int) $get(Subcategory::COLUMN_CATEGORY_ID))->value(Category::COLUMN_SLUG);

        return $schema->columns(1)->components([
            Section::make('مشخصات')
                ->columns(2)
                ->schema([
                    ImageSelect::images(Select::make(Subcategory::COLUMN_CATEGORY_ID), 'category', fn (): array => app(AdminOptions::class)->names('category'))
                        ->label('دسته اصلی')
                        ->required()
                        ->live()
                        ->rules(['exists:categories,id'])
                        ->helperText('با تغییر دسته، زیردسته با محصولاتش به دستهٔ تازه منتقل می‌شود و آدرس قبلی به آدرس تازه هدایت می‌شود.')
                        ->visible($picksCategory)
                        ->columnSpanFull(),
                    TextInput::make(Subcategory::COLUMN_NAME_FA)
                        ->label('نام فارسی')
                        ->required()
                        ->maxLength(255)
                        ->rules(fn (?Subcategory $record): array => $rules($record, Subcategory::COLUMN_NAME_FA)),
                    TextInput::make(Subcategory::COLUMN_NAME_EN)
                        ->label('نام انگلیسی')
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->maxLength(255)
                        ->rules(fn (?Subcategory $record): array => $rules($record, Subcategory::COLUMN_NAME_EN)),
                    TextInput::make(Subcategory::COLUMN_SLUG)
                        ->label('نامک (آدرس)')
                        ->prefix(fn (Get $get): string => '/categories/'.$categorySlug($get).'/')
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->maxLength(255)
                        ->rules(fn (?Subcategory $record): array => $rules($record, Subcategory::COLUMN_SLUG))
                        ->helperText('خالی بگذارید تا از نام انگلیسی (یا فارسی) ساخته شود.'),
                    Toggle::make(Subcategory::COLUMN_IS_ACTIVE)
                        ->label('فعال')
                        ->default(true),
                    DescriptionEditor::make(Subcategory::COLUMN_DESCRIPTION, maxLength: 5000)
                        ->columnSpanFull(),
                ]),
            Section::make('تصویرها')
                ->columns(3)
                ->schema([
                    ImageUpload::make(Subcategory::IMAGE_ICON, 'category_icon')->label('آیکن'),
                    ImageUpload::make(Subcategory::IMAGE_BANNER_DESKTOP, 'category_banner_desktop')->label('بنر دسکتاپ و تبلت'),
                    ImageUpload::make(Subcategory::IMAGE_BANNER_MOBILE, 'category_banner_mobile')->label('بنر موبایل'),
                ]),
            Section::make('سئو')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make(Subcategory::COLUMN_SEO_TITLE)->label('عنوان صفحه')->maxLength(255),
                    Textarea::make(Subcategory::COLUMN_SEO_DESCRIPTION)->label('توضیحات متا')->rows(3)->maxLength(500),
                ]),
        ]);
    }
}
