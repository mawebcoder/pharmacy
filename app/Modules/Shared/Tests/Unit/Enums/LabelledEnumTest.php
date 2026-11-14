<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Enums\Gender;

it('lists enum cases as value => Persian label for selects', function (): void {
    expect(Gender::options())->toBe(['female' => 'خانم', 'male' => 'آقا'])
        ->and(AdminRole::options())->toBe(['super_admin' => 'مدیر ارشد', 'operator' => 'اپراتور', 'content_editor' => 'ویراستار محتوا']);
});

it('labels a stored value and falls back to the value itself', function (): void {
    expect(AdminRole::labelFor('operator'))->toBe('اپراتور')
        ->and(AdminRole::labelFor('warehouse'))->toBe('warehouse');
});
