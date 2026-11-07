<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Locations\Enums\CityType;

it('lists enum cases as value => Persian label for selects', function (): void {
    expect(CityType::options())->toBe(['city' => 'شهر', 'village' => 'روستا'])
        ->and(AdminRole::options())->toBe(['super_admin' => 'مدیر ارشد', 'operator' => 'اپراتور', 'content_editor' => 'ویراستار محتوا']);
});

it('labels a stored value and falls back to the value itself', function (): void {
    expect(AdminRole::labelFor('operator'))->toBe('اپراتور')
        ->and(AdminRole::labelFor('warehouse'))->toBe('warehouse');
});
