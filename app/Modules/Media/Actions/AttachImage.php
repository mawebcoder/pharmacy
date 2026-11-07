<?php

declare(strict_types=1);

namespace App\Modules\Media\Actions;

use App\Modules\Media\Contracts\HasImagePresets;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Rules\ImageMatchesPreset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Stores an uploaded image in one of a model's image collections, replacing the previous one (roadmap D13.10).
 * Used outside the admin panel (the P2 API); Filament's upload field does the same through the media plugin.
 * The file is validated against the collection's preset; processing then runs on the "media" queue.
 */
final class AttachImage
{
    /**
     * @throws ValidationException
     */
    #[\NoDiscard]
    public function __invoke(HasImagePresets $owner, string $collection, UploadedFile $file, ?string $altFa = null): Media
    {
        $preset = $owner->imagePresets()[$collection] ?? throw new InvalidArgumentException("No image collection [{$collection}].");

        Validator::make(['image' => $file], ['image' => [ImageMatchesPreset::for($preset)]])->validate();

        /** @var Media $media */
        $media = $owner->addMedia($file)
            ->withCustomProperties(array_filter([Media::PROPERTY_ALT_FA => $altFa]))
            ->toMediaCollection($collection);

        return $media;
    }
}
