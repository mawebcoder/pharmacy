<?php

declare(strict_types=1);

namespace App\Modules\Shared\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

/**
 * Developer scaffolding: creates app/Modules/{Name}/{Name}ServiceProvider.php and registers it in bootstrap/providers.php.
 * Folders such as Models/, Data/ or Actions/ are added later by the steps that need them.
 */
final class MakeModuleCommand extends Command
{
    protected $signature = 'make:module {name : Module name in StudlyCase, e.g. Catalog}';

    protected $description = 'Create a module in app/Modules and register its service provider';

    public function handle(Filesystem $files): int
    {
        $name = (string) $this->argument('name');

        if (preg_match('/^[A-Z][A-Za-z]+$/', $name) !== 1) {
            $this->components->error('The module name must be StudlyCase letters only, e.g. "Catalog".');

            return self::FAILURE;
        }

        $path = $this->laravel->path("Modules/{$name}/{$name}ServiceProvider.php");

        if ($files->exists($path)) {
            $this->components->error("Module [{$name}] already exists.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $this->providerSource($name));

        ServiceProvider::addProviderToBootstrapFile("App\\Modules\\{$name}\\{$name}ServiceProvider");

        $this->components->info("Module [{$name}] created at app/Modules/{$name}.");

        return self::SUCCESS;
    }

    private function providerSource(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Modules\\{$name};

            use App\\Modules\\Shared\\ModuleServiceProvider;

            final class {$name}ServiceProvider extends ModuleServiceProvider
            {
                //
            }

            PHP;
    }
}
