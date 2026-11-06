<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Lists the modules in app/Modules: every folder that contains its own "{Name}ServiceProvider.php".
 */
final class Modules
{
    /**
     * @return list<string> Module names, e.g. ['Cart', 'Catalog', …], sorted alphabetically.
     */
    public static function names(?string $modulesPath = null): array
    {
        $modulesPath ??= app_path('Modules');

        $names = [];

        foreach (glob($modulesPath.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);

            if (is_file("{$directory}/{$name}ServiceProvider.php")) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }
}
