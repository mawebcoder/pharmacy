<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Banners\Pages;

use App\Modules\Content\Actions\SaveBanner;
use App\Modules\Content\Data\BannerData;
use App\Modules\Content\Exceptions\BannerChangeRefused;
use App\Modules\Content\Filament\Resources\Banners\BannerForm;
use App\Modules\Content\Filament\Resources\Banners\BannerResource;
use App\Modules\Content\Models\Banner;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class EditBanner extends EditRecord
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [BannerResource::deleteAction(), RestoreAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return BannerForm::withPickers($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Banner $record */
        try {
            return app(SaveBanner::class)(BannerData::fromArray([...$data, Banner::COLUMN_LINK_ID => BannerForm::toLinkId($data)]), $record);
        } catch (BannerChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();
            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }
}
