<?php

declare(strict_types=1);

namespace App\Modules\Settings\Filament\Resources\Pharmacists;

use App\Modules\Media\Filament\Forms\ImageUpload;
use App\Modules\Settings\Actions\DeletePharmacist;
use App\Modules\Settings\Actions\SavePharmacist;
use App\Modules\Settings\Data\PharmacistData;
use App\Modules\Settings\Filament\Resources\Pharmacists\Pages\ManagePharmacists;
use App\Modules\Settings\Http\Requests\SavePharmacistRequest;
use App\Modules\Settings\Models\Pharmacist;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * «داروسازان» (roadmap D3.6, S30): name, council number, photo and bio; one is the pharmacist in charge, shown first
 * on /pharmacist and named on every product page.
 */
final class PharmacistResource extends Resource
{
    protected static ?string $model = Pharmacist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'داروساز';

    protected static ?string $pluralModelLabel = 'داروسازان';

    public static function form(Schema $schema): Schema
    {
        $rules = SavePharmacistRequest::baseRules();

        return $schema
            ->columns(2)
            ->components([
                TextInput::make(Pharmacist::COLUMN_FULL_NAME)
                    ->label('نام و نام خانوادگی')
                    ->required()
                    ->maxLength(80)
                    ->placeholder('دکتر مریم احمدی')
                    ->rules($rules[Pharmacist::COLUMN_FULL_NAME]),
                TextInput::make(Pharmacist::COLUMN_LICENSE_NUMBER)
                    ->label('شماره نظام پزشکی')
                    ->required()
                    ->maxLength(20)
                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                    ->rules($rules[Pharmacist::COLUMN_LICENSE_NUMBER])
                    ->validationMessages(['regex' => 'شماره نظام پزشکی فقط عدد است.']),
                Toggle::make(Pharmacist::COLUMN_IS_RESPONSIBLE)
                    ->label('داروساز مسئول')
                    ->helperText('نام او در صفحه همه محصولات و اول صفحه «داروساز مسئول» می‌آید. فقط یک نفر مسئول است؛ با روشن‌کردن، از دیگری برداشته می‌شود.')
                    ->inline(false)
                    ->columnSpanFull(),
                ImageUpload::make(Pharmacist::IMAGE_PHOTO, 'pharmacist_photo')->label('عکس'),
                Textarea::make(Pharmacist::COLUMN_BIO)->label('معرفی کوتاه')->rows(4)->maxLength(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(Pharmacist::COLUMN_SORT)
            ->paginated(false)
            ->columns([
                TextColumn::make(Pharmacist::COLUMN_FULL_NAME)->label('نام'),
                TextColumn::make(Pharmacist::COLUMN_LICENSE_NUMBER)->label('شماره نظام پزشکی'),
                IconColumn::make(Pharmacist::COLUMN_IS_RESPONSIBLE)->label('مسئول')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Pharmacist $record, array $data): Pharmacist => app(SavePharmacist::class)(PharmacistData::fromArray($data), $record)),
                DeleteAction::make()
                    ->using(function (Pharmacist $record, DeletePharmacist $deletePharmacist): bool {
                        $deletePharmacist($record);

                        return true;
                    }),
            ]);
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label('افزودن داروساز')
            ->using(fn (array $data): Pharmacist => app(SavePharmacist::class)(PharmacistData::fromArray($data)));
    }

    public static function getPages(): array
    {
        return ['index' => ManagePharmacists::route('/')];
    }
}
