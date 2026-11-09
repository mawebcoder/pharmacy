<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Brands\Schemas;

use App\Modules\Catalog\Http\Requests\StoreBrandRequest;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Media\Filament\Forms\ImageUpload;
use App\Modules\Shared\Support\Countries;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Field rules come from StoreBrandRequest::baseRules() so admin and HTTP validation never drift (roadmap C5).
 */
final class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = fn (?Brand $record, string $field): array => StoreBrandRequest::baseRules($record?->getId())[$field];

        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Brand::COLUMN_NAME_FA)
                            ->label('نام فارسی')
                            ->required()
                            ->maxLength(255)
                            ->rules(fn (?Brand $record): array => $rules($record, Brand::COLUMN_NAME_FA)),
                        TextInput::make(Brand::COLUMN_NAME_EN)
                            ->label('نام انگلیسی')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->maxLength(255)
                            ->rules(fn (?Brand $record): array => $rules($record, Brand::COLUMN_NAME_EN)),
                        TextInput::make(Brand::COLUMN_SLUG)
                            ->label('نامک (آدرس)')
                            ->prefix('/brands/')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->maxLength(255)
                            ->rules(fn (?Brand $record): array => $rules($record, Brand::COLUMN_SLUG))
                            ->helperText(fn (?Brand $record): string => $record instanceof Brand
                                ? 'تغییر نامک، پیوندهای قبلی این برند را از کار می‌اندازد.'
                                : 'خالی بگذارید تا از نام انگلیسی (یا فارسی) ساخته شود.'),
                        Select::make(Brand::COLUMN_COUNTRY)
                            ->label('کشور')
                            ->options(Countries::options())
                            ->searchable()
                            ->rules(fn (?Brand $record): array => $rules($record, Brand::COLUMN_COUNTRY)),
                        Toggle::make(Brand::COLUMN_IS_ACTIVE)
                            ->label('فعال')
                            ->helperText('برند غیرفعال در سایت نمایش داده نمی‌شود.')
                            ->default(true),
                    ]),
                Section::make('معرفی')
                    ->schema([
                        Textarea::make(Brand::COLUMN_DESCRIPTION)
                            ->label('توضیحات')
                            ->rows(5)
                            ->maxLength(5000)
                            ->rules(fn (?Brand $record): array => $rules($record, Brand::COLUMN_DESCRIPTION)),
                    ]),
                Section::make('تصویر')
                    ->schema([
                        ImageUpload::make(Brand::IMAGE_LOGO, 'brand_logo')
                            ->label('لوگو'),
                    ]),
                Section::make('سئو')
                    ->description('خالی بگذارید تا از نام و توضیحات برند استفاده شود.')
                    ->collapsible()
                    ->schema([
                        TextInput::make(Brand::COLUMN_SEO_TITLE)
                            ->label('عنوان صفحه')
                            ->maxLength(255)
                            ->rules(fn (?Brand $record): array => $rules($record, Brand::COLUMN_SEO_TITLE)),
                        Textarea::make(Brand::COLUMN_SEO_DESCRIPTION)
                            ->label('توضیحات متا')
                            ->rows(3)
                            ->maxLength(500)
                            ->rules(fn (?Brand $record): array => $rules($record, Brand::COLUMN_SEO_DESCRIPTION)),
                    ]),
            ]);
    }
}
