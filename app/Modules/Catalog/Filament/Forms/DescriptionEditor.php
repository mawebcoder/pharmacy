<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Forms;

use Filament\Forms\Components\RichEditor;

/**
 * The rich editor for a long description (product, brand, category, subcategory). Stored as HTML; the storefront shows
 * it through DescriptionHtml, which keeps only what this toolbar makes. Short descriptions stay plain text fields.
 * The editor validates its own state (a document, not a string until saved), so the length limit counts the text.
 */
final class DescriptionEditor
{
    public static function make(string $field, int $maxLength): RichEditor
    {
        return RichEditor::make($field)
            ->label('توضیحات')
            ->maxLength($maxLength)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3'],
                ['bulletList', 'orderedList', 'blockquote'],
                ['undo', 'redo'],
            ])
            ->extraInputAttributes(['style' => 'min-height: 12rem']);
    }
}
