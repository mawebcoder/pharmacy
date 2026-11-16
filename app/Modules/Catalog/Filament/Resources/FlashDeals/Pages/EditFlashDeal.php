<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\FlashDeals\Pages;

use App\Modules\Catalog\Actions\SaveFlashDeal;
use App\Modules\Catalog\Data\FlashDealData;
use App\Modules\Catalog\Exceptions\FlashDealRefused;
use App\Modules\Catalog\Filament\Resources\FlashDeals\FlashDealResource;
use App\Modules\Catalog\Models\FlashDeal;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class EditFlashDeal extends EditRecord
{
    protected static string $resource = FlashDealResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var FlashDeal $record */
        try {
            return app(SaveFlashDeal::class)(FlashDealData::fromArray($data), $record);
        } catch (FlashDealRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();

            throw new Halt;
        }
    }
}
