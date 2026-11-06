<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

it('accepts only strong admin passwords', function (string $password, bool $passes): void {
    $validator = Validator::make(['password' => $password], ['password' => Password::default()]);

    expect($validator->passes())->toBe($passes);
})->with([
    'meets every rule' => ['Daroo-Khane-1405!', true],
    'shorter than 12 characters' => ['Dk-1405!xyZ', false],
    'no symbol' => ['DarooKhane1405', false],
    'no number' => ['Daroo-Khane-!!', false],
    'single case' => ['daroo-khane-1405!', false],
]);
