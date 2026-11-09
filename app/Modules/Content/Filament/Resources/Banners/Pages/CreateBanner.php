<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Banners\Pages;

use App\Modules\Content\Actions\SaveBanner;
use App\Modules\Content\Data\BannerData;
use App\Modules\Content\Exceptions\BannerChangeRefused;
use App\Modules\Content\Filament\Resources\Banners\BannerForm;
use App\Modules\Content\Filament\Resources\Banners\BannerResource;
use App\Modules\Content\Models\Banner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class CreateBanner extends CreateRecord
{
    protected static string $resource = BannerResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveBanner::class)(BannerData::fromArray([...$data, Banner::COLUMN_LINK_ID => BannerForm::toLinkId($data)]));
        } catch (BannerChangeRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();
            throw new Halt($refused->getMessage(), $refused->getCode(), $refused);
        }
    }
}
