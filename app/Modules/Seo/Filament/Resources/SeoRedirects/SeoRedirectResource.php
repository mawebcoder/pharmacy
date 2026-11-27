<?php

declare(strict_types=1);

namespace App\Modules\Seo\Filament\Resources\SeoRedirects;

use App\Modules\Seo\Actions\DeleteRedirect;
use App\Modules\Seo\Actions\SaveRedirect;
use App\Modules\Seo\Data\SeoRedirectData;
use App\Modules\Seo\Enums\RedirectSource;
use App\Modules\Seo\Exceptions\RedirectRefused;
use App\Modules\Seo\Filament\Resources\SeoRedirects\Pages\ManageSeoRedirects;
use App\Modules\Seo\Http\Requests\SaveSeoRedirectRequest;
use App\Modules\Seo\Models\SeoRedirect;
use App\Modules\Shared\Support\PersianDigits;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * «ریدایرکت‌ها» (roadmap D3.6, S38): old addresses sent on to new ones, added by hand or by a slug change, with how
 * often each was used. Saved through SaveRedirect (one redirect per old address, no chains or loops).
 */
final class SeoRedirectResource extends Resource
{
    protected static ?string $model = SeoRedirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'ریدایرکت';

    protected static ?string $pluralModelLabel = 'ریدایرکت‌ها';

    public static function form(Schema $schema): Schema
    {
        $rules = SaveSeoRedirectRequest::baseRules();

        return $schema
            ->columns(2)
            ->components([
                TextInput::make(SeoRedirect::COLUMN_FROM_PATH)
                    ->label('نشانی قدیم')
                    ->required()
                    ->maxLength(500)
                    ->rules($rules[SeoRedirect::COLUMN_FROM_PATH])
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->placeholder('/products/old-name')
                    ->helperText('مسیر قدیمی در همین سایت؛ نشانی کامل هم پذیرفته می‌شود.'),
                TextInput::make(SeoRedirect::COLUMN_TO_PATH)
                    ->label('نشانی تازه')
                    ->required()
                    ->maxLength(500)
                    ->rules($rules[SeoRedirect::COLUMN_TO_PATH])
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->placeholder('/products/new-name')
                    ->helperText('مسیری در همین سایت یا نشانی کامل https:// سایت دیگر.'),
                Select::make(SeoRedirect::COLUMN_STATUS_CODE)
                    ->label('نوع')
                    ->options([301 => 'دائمی (۳۰۱)', 302 => 'موقت (۳۰۲)'])
                    ->default(301)
                    ->required()
                    ->rules($rules[SeoRedirect::COLUMN_STATUS_CODE])
                    ->helperText('برای نشانی‌ای که برنمی‌گردد «دائمی» را نگه دارید.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->description('بازدید از نشانی قدیم به نشانی تازه فرستاده می‌شود. با تغییر نامک محصول، دسته، برند، برچسب، کالکشن یا صفحه، ریدایرکت خودکار ساخته می‌شود.')
            ->columns([
                TextColumn::make(SeoRedirect::COLUMN_FROM_PATH)->label('نشانی قدیم')->searchable()->wrap()->extraAttributes(['dir' => 'ltr']),
                TextColumn::make(SeoRedirect::COLUMN_TO_PATH)->label('نشانی تازه')->searchable()->wrap()->extraAttributes(['dir' => 'ltr']),
                TextColumn::make(SeoRedirect::COLUMN_STATUS_CODE)->label('نوع')->formatStateUsing(fn (int $state): string => $state === 301 ? 'دائمی' : 'موقت'),
                TextColumn::make(SeoRedirect::COLUMN_SOURCE)->label('ساخته‌شده با')->badge()->formatStateUsing(fn (RedirectSource $state): string => $state->label()),
                TextColumn::make(SeoRedirect::COLUMN_HITS)->label('بازدید')->sortable()->formatStateUsing(fn (int $state): string => PersianDigits::toPersian($state)),
                TextColumn::make(SeoRedirect::COLUMN_LAST_HIT_AT)->label('آخرین بازدید')->jalaliDateTime()->placeholder('—'), // @phpstan-ignore method.notFound (macro)
            ])
            ->recordActions([
                EditAction::make()->using(fn (SeoRedirect $record, array $data): SeoRedirect => self::save($data, $record)),
                DeleteAction::make()->using(function (SeoRedirect $record, DeleteRedirect $delete): bool {
                    $delete($record);

                    return true;
                }),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('افزودن ریدایرکت')
            ->using(fn (array $data): SeoRedirect => self::save($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data, ?SeoRedirect $redirect = null): SeoRedirect
    {
        try {
            return app(SaveRedirect::class)(SeoRedirectData::fromArray($data), RedirectSource::Admin, $redirect);
        } catch (RedirectRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();
            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageSeoRedirects::route('/')];
    }
}
