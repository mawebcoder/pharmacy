<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\Coupons\Pages;

use App\Modules\Cart\Actions\SaveCoupon;
use App\Modules\Cart\Data\CouponData;
use App\Modules\Cart\Filament\Resources\Coupons\CouponForm;
use App\Modules\Cart\Filament\Resources\Coupons\CouponResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveCoupon::class)(CouponData::fromArray(CouponForm::toData($data)));
    }
}
