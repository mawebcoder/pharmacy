<?php

declare(strict_types=1);

use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\System\Models\IntegrationLog;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

arch('application code uses strict types')
    ->expect(['App', 'Database'])
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

// config/*.php is not autoloaded, so this covers everything except the config files.
arch('env() is only read in config files')
    ->expect('env')
    ->not->toBeUsed();

/*
| Module boundaries (roadmap C3 rule 1) and the C5 building blocks, generated for every module in app/Modules.
*/
$modules = array_map(basename(...), glob(dirname(__DIR__, 2).'/app/Modules/*', GLOB_ONLYDIR) ?: []);

foreach ($modules as $module) {
    $otherModulesModels = array_values(array_map(
        fn (string $other): string => "App\\Modules\\{$other}\\Models",
        array_diff($modules, [$module]),
    ));

    arch("{$module} module does not use other modules' models")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse($otherModulesModels);

    arch("{$module} DTOs are final and readonly")
        ->expect("App\\Modules\\{$module}\\Data")
        ->toBeFinal()
        ->toBeReadonly();

    arch("{$module} models record their changes in the audit log")
        ->expect("App\\Modules\\{$module}\\Models")
        ->toUseTrait(LogsActivity::class)
        // Log tables are records themselves; auditing them would log every log row.
        ->ignoring(IntegrationLog::class);

    arch("{$module} enums have Persian labels")
        ->expect("App\\Modules\\{$module}\\Enums")
        ->enums()
        ->toImplement(HasLabel::class);

    arch("{$module} actions are final and invokable")
        ->expect("App\\Modules\\{$module}\\Actions")
        ->toBeFinal()
        ->toBeInvokable();
}
