<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Attributes\RelationManagers;

use App\Modules\Catalog\Actions\DeleteAttributeValue;
use App\Modules\Catalog\Actions\SaveAttributeValue;
use App\Modules\Catalog\Exceptions\AttributeChangeRefused;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The choices of an attribute, in the order shown in filters and product forms (drag to reorder). Changing them is
 * an update of the attribute; values used by products cannot be deleted.
 */
final class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'مقدارها';

    protected static ?string $modelLabel = 'مقدار';

    protected static ?string $pluralModelLabel = 'مقدارها';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! (auth('admin')->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make(AttributeValue::COLUMN_VALUE_FA)->label('مقدار')->required()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('products'))
            ->recordTitleAttribute(AttributeValue::COLUMN_VALUE_FA)
            ->reorderable(AttributeValue::COLUMN_SORT, fn (): bool => ! $this->isReadOnly())
            ->defaultSort(AttributeValue::COLUMN_SORT)
            ->paginated(false)
            ->columns([
                TextColumn::make(AttributeValue::COLUMN_VALUE_FA)->label('مقدار')->searchable(),
                TextColumn::make('products_count')->label('محصولات')->numeric(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->using(fn (array $data): AttributeValue => $this->save((string) $data[AttributeValue::COLUMN_VALUE_FA])),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->using(fn (AttributeValue $record, array $data): AttributeValue => $this->save((string) $data[AttributeValue::COLUMN_VALUE_FA], $record)),
                DeleteAction::make()
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->using(function (AttributeValue $record, DeleteAttributeValue $deleteValue, DeleteAction $action): bool {
                        try {
                            $deleteValue($record);
                        } catch (AttributeChangeRefused $refused) {
                            $action->failureNotificationTitle($refused->getMessage());

                            return false;
                        }

                        return true;
                    }),
            ]);
    }

    private function save(string $valueFa, ?AttributeValue $value = null): AttributeValue
    {
        /** @var Attribute $attribute */
        $attribute = $this->getOwnerRecord();

        try {
            return app(SaveAttributeValue::class)($attribute, $valueFa, $value);
        } catch (AttributeChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();

            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }
}
