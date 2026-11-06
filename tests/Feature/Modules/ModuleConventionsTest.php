<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Gate;

it('finds the policy of a module model inside the same module', function (): void {
    // Throwaway classes in a module namespace; S07+ will add real ones.
    if (! class_exists('App\Modules\Catalog\Models\ConventionProbe')) {
        eval('namespace App\Modules\Catalog\Models; final class ConventionProbe extends \Illuminate\Database\Eloquent\Model {}');
        eval('namespace App\Modules\Catalog\Policies; final class ConventionProbePolicy {}');
    }

    expect(Gate::getPolicyFor('App\Modules\Catalog\Models\ConventionProbe'))
        ->toBeInstanceOf('App\Modules\Catalog\Policies\ConventionProbePolicy');
});

it('finds the factory of a module model inside the same module', function (): void {
    expect(Factory::resolveFactoryName('App\Modules\Catalog\Models\Brand'))
        ->toBe('App\Modules\Catalog\Database\Factories\BrandFactory');
});

it('keeps Laravel conventions for models outside modules', function (): void {
    expect(Factory::resolveFactoryName(User::class))->toBe(UserFactory::class)
        ->and(User::factory()->make())->toBeInstanceOf(User::class);
});
