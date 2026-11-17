<?php

declare(strict_types=1);

namespace App\Modules\Settings\Filament\Resources\TrustBadges;

use App\Modules\Media\Filament\Forms\ImageUpload;
use App\Modules\Settings\Actions\DeleteTrustBadge;
use App\Modules\Settings\Actions\SaveTrustBadge;
use App\Modules\Settings\Data\TrustBadgeData;
use App\Modules\Settings\Exceptions\TrustBadgeRefused;
use App\Modules\Settings\Filament\Resources\TrustBadges\Pages\ManageTrustBadges;
use App\Modules\Settings\Http\Requests\SaveTrustBadgeRequest;
use App\Modules\Settings\Models\TrustBadge;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * «نمادهای اعتماد» (roadmap D3.6, S35): the footer's seals and licences. Paste e-Namad's or Samandehi's code as they
 * hand it out (cleaned to their link and image), or upload an image with an optional link; dragged into order.
 */
final class TrustBadgeResource extends Resource
{
    protected static ?string $model = TrustBadge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'نماد اعتماد';

    protected static ?string $pluralModelLabel = 'نمادهای اعتماد';

    public static function form(Schema $schema): Schema
    {
        $rules = SaveTrustBadgeRequest::baseRules();

        return $schema
            ->columns(2)
            ->components([
                TextInput::make(TrustBadge::COLUMN_TITLE)
                    ->label('عنوان')
                    ->required()
                    ->maxLength(80)
                    ->rules($rules[TrustBadge::COLUMN_TITLE])
                    ->helperText('متن جایگزین تصویر و نام در مدیریت؛ مثلاً «نماد اعتماد الکترونیکی».'),
                Toggle::make(TrustBadge::COLUMN_IS_ACTIVE)->label('نمایش در سایت')->default(true)->inline(false),
                Textarea::make(TrustBadge::COLUMN_EMBED_CODE)
                    ->label('کد نماد')
                    ->rows(4)
                    ->maxLength(5000)
                    ->live(onBlur: true)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->helperText('کدی که enamad.ir یا samandehi.ir می‌دهد را کامل اینجا بچسبانید. اگر کد ندارید، تصویر بارگذاری کنید.')
                    ->columnSpanFull(),
                ImageUpload::make(TrustBadge::IMAGE, 'trust_badge')
                    ->label('تصویر')
                    ->required(fn (Get $get): bool => blank($get(TrustBadge::COLUMN_EMBED_CODE)))
                    ->visible(fn (Get $get): bool => blank($get(TrustBadge::COLUMN_EMBED_CODE))),
                TextInput::make(TrustBadge::COLUMN_LINK_URL)
                    ->label('پیوند تصویر')
                    ->url()
                    ->startsWith(['https://'])
                    ->maxLength(500)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->helperText('اختیاری؛ مثلاً صفحه استعلام مجوز. در برگه جدید باز می‌شود.')
                    ->visible(fn (Get $get): bool => blank($get(TrustBadge::COLUMN_EMBED_CODE))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable(TrustBadge::COLUMN_SORT, fn (): bool => auth('admin')->user()?->can('Update:TrustBadge') ?? false)
            ->defaultSort(TrustBadge::COLUMN_SORT)
            ->paginated(false)
            ->description('به همین ترتیب در پانویس همه صفحه‌ها نمایش داده می‌شوند؛ با کشیدن جابه‌جا کنید.')
            ->columns([
                TextColumn::make(TrustBadge::COLUMN_TITLE)->label('عنوان'),
                TextColumn::make('kind')
                    ->label('نوع')
                    ->badge()
                    ->state(fn (TrustBadge $record): string => $record->getEmbedCode() !== null ? 'کد نماد' : 'تصویر'),
                IconColumn::make(TrustBadge::COLUMN_IS_ACTIVE)->label('نمایش')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->using(fn (TrustBadge $record, array $data): TrustBadge => self::save($data, $record)),
                DeleteAction::make()
                    ->using(function (TrustBadge $record, DeleteTrustBadge $deleteBadge): bool {
                        $deleteBadge($record);

                        return true;
                    }),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('افزودن نماد')
            ->using(fn (array $data): TrustBadge => self::save($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data, ?TrustBadge $badge = null): TrustBadge
    {
        try {
            return app(SaveTrustBadge::class)(TrustBadgeData::fromArray($data), $badge);
        } catch (TrustBadgeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();
            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageTrustBadges::route('/')];
    }
}
