<?php

declare(strict_types=1);

use App\Modules\Media\Models\Media;
use App\Modules\Pricing\Models\PriceImportRow;
use App\Modules\Pricing\Models\ProviderPriceChange;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Search\Models\SearchLog;
use App\Modules\Shared\Contracts\HasLabel;
use App\Modules\System\Models\IntegrationLog;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

// Records are deleted one at a time, so each delete goes through its own checks (decided 2026-11-07).
arch('admin tables have no bulk delete')
    ->expect('App')
    ->not->toUse([DeleteBulkAction::class, ForceDeleteBulkAction::class]);

// Models are written through setters and save(), never mass assigned (roadmap C4, decided 2026-11-07).
arch('models declare no fillable attributes')
    ->expect(['App\\Models', 'App\\Modules'])
    ->not->toHaveAttribute(Fillable::class);

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
        // Log tables are records themselves; auditing them would log every log row (the price history tables of S14
        // and S15 and the import rows of S16 are such records). Media rows change on every processing step; changes to what an image belongs to
        // are logged on the owner.
        ->ignoring([IntegrationLog::class, Media::class, SearchLog::class, ProviderPriceChange::class, VariantPrice::class, PriceImportRow::class]);

    arch("{$module} enums have Persian labels")
        ->expect("App\\Modules\\{$module}\\Enums")
        ->enums()
        ->toImplement(HasLabel::class);

    arch("{$module} actions are final and invokable")
        ->expect("App\\Modules\\{$module}\\Actions")
        ->toBeFinal()
        ->toBeInvokable();
}
