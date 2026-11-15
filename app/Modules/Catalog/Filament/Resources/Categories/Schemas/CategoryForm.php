<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\Schemas;

use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Media\Filament\Forms\ImageUpload;
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
                    ->columns(['md' => 4])
                    ->schema([
                        ImageUpload::make(Category::IMAGE_ICON, 'category_icon')
                            ->label('آیکن'),
                        // Small, like the icon it holds (owner, 2026-11-15).
                        ImageUpload::make(Category::IMAGE_MENU_ICON, 'category_menu_icon')
                            ->label('آیکن منو (کوچک)')
                            ->imagePreviewHeight('48'),
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
}
