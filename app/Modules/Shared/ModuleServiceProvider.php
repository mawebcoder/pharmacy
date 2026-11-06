<?php

declare(strict_types=1);

namespace App\Modules\Shared;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ReflectionClass;

/**
 * Base provider for every module in app/Modules (roadmap C3).
 *
 * A module's provider extends this class and gets everything below loaded automatically
 * when the matching folder or file exists inside the module:
 *
 * - Database/Migrations            → migrations
 * - Routes/web.php                 → routes with the "web" middleware group
 * - resources/views                → Blade views as "{alias}::view.name"
 * - Livewire/ and resources/views/livewire → Livewire components as "{alias}::component-name"
 * - Console/*Command.php           → Artisan commands
 *
 * The alias is the module name in kebab case, e.g. "catalog" for the Catalog module.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadModuleMigrations();
        $this->loadModuleRoutes();
        $this->loadModuleViews();
        $this->registerModuleLivewireComponents();
        $this->registerModuleCommands();
    }

    /**
     * The module name, e.g. "Catalog" for CatalogServiceProvider.
     */
    public function moduleName(): string
    {
        return Str::beforeLast(class_basename(static::class), 'ServiceProvider');
    }

    /**
     * The prefix used for the module's views and Livewire components, e.g. "catalog".
     */
    public function moduleAlias(): string
    {
        return Str::kebab($this->moduleName());
    }

    /**
     * The module's root namespace, e.g. "App\Modules\Catalog".
     */
    public function moduleNamespace(): string
    {
        return (new ReflectionClass(static::class))->getNamespaceName();
    }

    /**
     * An absolute path inside the module's folder.
     */
    public function modulePath(string $path = ''): string
    {
        $root = dirname((string) (new ReflectionClass(static::class))->getFileName());

        return $path === '' ? $root : $root.DIRECTORY_SEPARATOR.$path;
    }

    protected function loadModuleMigrations(): void
    {
        if (is_dir($path = $this->modulePath('Database/Migrations'))) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function loadModuleRoutes(): void
    {
        if ($this->app->routesAreCached() || ! is_file($path = $this->modulePath('Routes/web.php'))) {
            return;
        }

        Route::middleware('web')->group($path);
    }

    protected function loadModuleViews(): void
    {
        if (is_dir($path = $this->modulePath('resources/views'))) {
            $this->loadViewsFrom($path, $this->moduleAlias());
        }
    }

    protected function registerModuleLivewireComponents(): void
    {
        $classPath = $this->modulePath('Livewire');
        $viewPath = $this->modulePath('resources/views/livewire');

        if (! is_dir($classPath) && ! is_dir($viewPath)) {
            return;
        }

        Livewire::addNamespace(
            namespace: $this->moduleAlias(),
            viewPath: $viewPath,
            classNamespace: $this->moduleNamespace().'\\Livewire',
            classPath: $classPath,
            classViewPath: $viewPath,
        );
    }

    protected function registerModuleCommands(): void
    {
        if (! $this->app->runningInConsole() || ! is_dir($path = $this->modulePath('Console'))) {
            return;
        }

        $commands = collect(glob($path.'/*Command.php') ?: [])
            ->map(fn (string $file): string => $this->moduleNamespace().'\\Console\\'.basename($file, '.php'))
            ->all();

        $this->commands($commands);
    }
}
