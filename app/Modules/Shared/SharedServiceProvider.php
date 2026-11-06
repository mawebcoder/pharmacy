<?php

declare(strict_types=1);

namespace App\Modules\Shared;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Shared kernel: base classes and conventions every module relies on.
 */
final class SharedServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->guessModulePolicyNames();
        $this->guessModuleFactoryNames();
    }

    /**
     * App\Modules\Catalog\Models\Brand → App\Modules\Catalog\Policies\BrandPolicy.
     * Models outside a module keep Laravel's convention (App\Models\User → App\Policies\UserPolicy).
     */
    private function guessModulePolicyNames(): void
    {
        Gate::guessPolicyNamesUsing(function (string $modelClass): string {
            if (preg_match('/^(App\\\\Modules\\\\[^\\\\]+)\\\\Models\\\\(.+)$/', $modelClass, $matches) === 1) {
                return "{$matches[1]}\\Policies\\{$matches[2]}Policy";
            }

            return 'App\\Policies\\'.Str::after($modelClass, 'App\\Models\\').'Policy';
        });
    }

    /**
     * App\Modules\Catalog\Models\Brand → App\Modules\Catalog\Database\Factories\BrandFactory.
     * Models outside a module keep Laravel's convention (App\Models\User → Database\Factories\UserFactory).
     */
    private function guessModuleFactoryNames(): void
    {
        Factory::guessFactoryNamesUsing(
            /**
             * @param  class-string<Model>  $modelClass
             * @return class-string<Factory<Model>>
             */
            function (string $modelClass): string {
                $factoryClass = preg_match('/^(App\\\\Modules\\\\[^\\\\]+)\\\\Models\\\\(.+)$/', $modelClass, $matches) === 1
                    ? "{$matches[1]}\\Database\\Factories\\{$matches[2]}Factory"
                    : 'Database\\Factories\\'.Str::after($modelClass, 'App\\Models\\').'Factory';

                /** @var class-string<Factory<Model>> $factoryClass */
                return $factoryClass;
            },
        );
    }
}
