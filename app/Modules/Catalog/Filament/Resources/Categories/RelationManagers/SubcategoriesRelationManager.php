<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Categories\RelationManagers;

use App\Modules\Catalog\Actions\DeleteSubcategory;
use App\Modules\Catalog\Actions\SaveSubcategory;
use App\Modules\Catalog\Data\SubcategoryData;
use App\Modules\Catalog\Exceptions\CategoryChangeRefused;
use App\Modules\Catalog\Filament\Resources\Subcategories\Schemas\SubcategoryForm;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Subcategory;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * «زیردسته‌ها» of a category (owner, 2026-11-15: their own table, two levels), in the order shown on the storefront
 * (drag to reorder). Changing them is an update of the category; a subcategory used by products cannot be deleted.
 */
final class SubcategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'subcategories';

    protected static ?string $title = 'زیردسته‌ها';

    protected static ?string $modelLabel = 'زیردسته';

    protected static ?string $pluralModelLabel = 'زیردسته‌ها';

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
        return SubcategoryForm::configure($schema, fn (): string => $this->category()->getSlug());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->recordTitleAttribute(Subcategory::COLUMN_NAME_FA)
            ->reorderable(Subcategory::COLUMN_SORT, fn (): bool => ! $this->isReadOnly())
            ->defaultSort(Subcategory::COLUMN_SORT)
            ->paginated(false)
            ->columns([
                ImageThumbnail::make(Subcategory::IMAGE_ICON, 'category_icon'),
                TextColumn::make(Subcategory::COLUMN_NAME_FA)
                    ->label('نام')
                    ->description(fn (Subcategory $record): ?string => $record->getNameEn())
                    ->searchable([Subcategory::COLUMN_NAME_FA, Subcategory::COLUMN_NAME_EN]),
                TextColumn::make(Subcategory::COLUMN_SLUG)->label('نامک')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make(Subcategory::COLUMN_IS_ACTIVE)
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'فعال' : 'غیرفعال')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([TrashedFilter::make()])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->using(fn (array $data, SaveSubcategory $save): Subcategory => $save($this->category(), SubcategoryData::fromArray($data))),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Subcategory $record): bool => ! $this->isReadOnly() && ! $record->trashed())
                    ->using(fn (Subcategory $record, array $data, SaveSubcategory $save): Subcategory => $save($this->category(), SubcategoryData::fromArray($data), $record)),
                DeleteAction::make()
                    ->visible(fn (Subcategory $record): bool => ! $this->isReadOnly() && ! $record->trashed())
                    ->using(function (Subcategory $record, DeleteSubcategory $delete, DeleteAction $action): bool {
                        try {
                            $delete($record);
                        } catch (CategoryChangeRefused $refused) {
                            $action->failureNotificationTitle($refused->getMessage());

                            return false;
                        }

                        return true;
                    }),
                RestoreAction::make()
                    ->visible(fn (Subcategory $record): bool => ! $this->isReadOnly() && $record->trashed()),
            ]);
    }

    private function category(): Category
    {
        /** @var Category $category */
        $category = $this->getOwnerRecord();

        return $category;
    }
}
