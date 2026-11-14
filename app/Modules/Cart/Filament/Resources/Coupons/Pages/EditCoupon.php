<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\Coupons\Pages;

use App\Modules\Cart\Actions\SaveCoupon;
use App\Modules\Cart\Data\CouponData;
use App\Modules\Cart\Filament\Resources\Coupons\CouponForm;
use App\Modules\Cart\Filament\Resources\Coupons\CouponResource;
use App\Modules\Cart\Models\Coupon;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->getRecord();

        return CouponForm::fill($coupon, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Coupon $record */
        return app(SaveCoupon::class)(CouponData::fromArray(CouponForm::toData($data)), $record);
    }
}
