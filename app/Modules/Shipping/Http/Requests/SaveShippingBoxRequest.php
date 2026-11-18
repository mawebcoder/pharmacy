<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Data\ShippingBoxData;
use App\Modules\Shipping\Models\ShippingBox;
use App\Modules\Shipping\Packing\BoxPacker;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a box (roadmap C5, S31); the Filament form applies the same baseRules(). The box's maximum is the
 * whole parcel with the box, above the box's own weight and at most the 6 kg a parcel may weigh.
 */
final class SaveShippingBoxRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public static function baseRules(): array
    {
        $side = ['required', 'integer', 'min:1', 'max:200'];

        return [
            ShippingBox::COLUMN_NAME => ['required', 'string', 'max:60'],
            ShippingBox::COLUMN_TIPAX_SIZE => ['nullable', 'integer', 'min:1', 'max:99'],
            ShippingBox::COLUMN_LENGTH_CM => $side,
            ShippingBox::COLUMN_WIDTH_CM => $side,
            ShippingBox::COLUMN_HEIGHT_CM => $side,
            ShippingBox::COLUMN_EMPTY_WEIGHT_G => ['required', 'integer', 'min:1', 'max:5000'],
            // The whole parcel, box included: more than the empty box, never above Tapin's 6 kg (owner, 2026-11-17).
            ShippingBox::COLUMN_MAX_WEIGHT_G => ['required', 'integer', 'gt:'.ShippingBox::COLUMN_EMPTY_WEIGHT_G, 'max:'.BoxPacker::MAX_PARCEL_WEIGHT_G],
            ShippingBox::COLUMN_IS_ACTIVE => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('Create:ShippingBox') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return self::baseRules();
    }

    public function toDto(): ShippingBoxData
    {
        return ShippingBoxData::fromArray($this->validated());
    }
}
