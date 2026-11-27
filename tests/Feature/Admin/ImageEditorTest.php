<?php

declare(strict_types=1);

use App\Modules\Media\Filament\Forms\ImageUpload;
use Filament\Forms\Components\FileUpload;

it('offers the image editor on every admin upload field, also the rich editor image uploads', function (): void {
    expect(FileUpload::make('file')->hasImageEditor())->toBeTrue()
        ->and(ImageUpload::make('logo', 'brand_logo')->hasImageEditor())->toBeTrue();
});
