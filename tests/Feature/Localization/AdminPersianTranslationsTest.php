<?php

declare(strict_types=1);

/*
| The admin must be fully Persian. Filament's own fa translation lags behind its English one, so the gaps are
| filled in lang/vendor/{namespace}/fa. This test fails when an upgrade brings English strings with no Persian.
*/

/**
 * Translation namespace => package directory.
 *
 * @return array<string, string>
 */
function adminTranslationPackages(): array
{
    return [
        'filament' => 'vendor/filament/support',
        'filament-actions' => 'vendor/filament/actions',
        'filament-forms' => 'vendor/filament/forms',
        'filament-infolists' => 'vendor/filament/infolists',
        'filament-notifications' => 'vendor/filament/notifications',
        'filament-panels' => 'vendor/filament/filament',
        'filament-query-builder' => 'vendor/filament/query-builder',
        'filament-schemas' => 'vendor/filament/schemas',
        'filament-tables' => 'vendor/filament/tables',
        'filament-widgets' => 'vendor/filament/widgets',
        'filament-shield' => 'vendor/bezhansalleh/filament-shield',
    ];
}

/**
 * @param  array<array-key, mixed>  $lines
 * @return list<string>
 */
function translationKeys(array $lines, string $prefix = ''): array
{
    $keys = [];

    foreach ($lines as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        $keys = is_array($value) ? [...$keys, ...translationKeys($value, $path)] : [...$keys, $path];
    }

    return $keys;
}

it('has a Persian translation for every Filament and Shield admin string', function (): void {
    $missing = [];

    foreach (adminTranslationPackages() as $namespace => $package) {
        $english = base_path("{$package}/resources/lang/en");

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($english, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($english) + 1);
            $persian = [];

            foreach ([base_path("{$package}/resources/lang/fa/{$relative}"), lang_path("vendor/{$namespace}/fa/{$relative}")] as $path) {
                if (is_file($path)) {
                    $persian = array_replace_recursive($persian, require $path);
                }
            }

            foreach (array_diff(translationKeys(require $file->getPathname()), translationKeys($persian)) as $key) {
                $missing[] = "{$namespace}::".substr($relative, 0, -4).".{$key}";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('serves the Persian strings in the admin', function (string $key, array $replace, string $expected): void {
    expect(__($key, $replace))->toBe($expected);
})->with([
    '2FA code boxes' => ['filament::components/input/one-time-code.aria_label', ['position' => 2, 'count' => 6], 'نویسه 2 از 6'],
    'skip link' => ['filament-panels::layout.skip_to_content.label', [], 'پرش به محتوای اصلی'],
    'date picker month' => ['filament-forms::components.date_time_picker.month_select.label', [], 'ماه'],
    'table yes' => ['filament-tables::table.columns.icon.boolean.true', [], 'بله'],
    'existing Filament Persian is kept' => ['filament-tables::table.empty.heading', ['model' => 'شهر'], 'شهر یافت نشد.'],
]);
