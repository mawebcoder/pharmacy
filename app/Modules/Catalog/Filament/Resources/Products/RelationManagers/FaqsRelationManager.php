<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use App\Modules\Catalog\Actions\SaveProductFaq;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductFaq;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Questions and answers for the product page FAQ tab; drag to reorder.
 */
final class FaqsRelationManager extends RelationManager
{
    use AuthorizesThroughProduct;

    protected static string $relationship = 'faqs';

    protected static ?string $title = 'پرسش‌های متداول';

    protected static ?string $modelLabel = 'پرسش';

    protected static ?string $pluralModelLabel = 'پرسش‌ها';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make(ProductFaq::COLUMN_QUESTION)->label('پرسش')->required()->maxLength(500),
                Textarea::make(ProductFaq::COLUMN_ANSWER)->label('پاسخ')->required()->rows(4)->maxLength(5000),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute(ProductFaq::COLUMN_QUESTION)
            ->reorderable(ProductFaq::COLUMN_SORT, fn (): bool => $this->canChange())
            ->defaultSort(ProductFaq::COLUMN_SORT)
            ->columns([
                TextColumn::make(ProductFaq::COLUMN_QUESTION)->label('پرسش')->wrap(),
                TextColumn::make(ProductFaq::COLUMN_ANSWER)->label('پاسخ')->limit(80)->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canChange())
                    ->using(fn (array $data, SaveProductFaq $saveFaq): ProductFaq => $saveFaq($this->product(), (string) $data[ProductFaq::COLUMN_QUESTION], (string) $data[ProductFaq::COLUMN_ANSWER])),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => $this->canChange())
                    ->using(fn (ProductFaq $record, array $data, SaveProductFaq $saveFaq): ProductFaq => $saveFaq($this->product(), (string) $data[ProductFaq::COLUMN_QUESTION], (string) $data[ProductFaq::COLUMN_ANSWER], $record)),
                DeleteAction::make()->visible(fn (): bool => $this->canChange()),
            ]);
    }

    private function product(): Product
    {
        /** @var Product */
        return $this->getOwnerRecord();
    }
}
