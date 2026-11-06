<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // Generate into a temporary app/ and bootstrap/ so the real project is never touched.
    $this->sandbox = storage_path('framework/testing/make-module-'.uniqid());
    File::ensureDirectoryExists("{$this->sandbox}/app");
    File::ensureDirectoryExists("{$this->sandbox}/bootstrap");
    File::put("{$this->sandbox}/bootstrap/providers.php", "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");

    $this->app->useAppPath("{$this->sandbox}/app");
    $this->app->useBootstrapPath("{$this->sandbox}/bootstrap");
});

afterEach(function (): void {
    File::deleteDirectory($this->sandbox);
});

it('creates the module provider and registers it', function (): void {
    $this->artisan('make:module', ['name' => 'Coupons'])->assertSuccessful();

    $provider = File::get("{$this->sandbox}/app/Modules/Coupons/CouponsServiceProvider.php");

    expect($provider)
        ->toContain('declare(strict_types=1);')
        ->toContain('namespace App\Modules\Coupons;')
        ->toContain('final class CouponsServiceProvider extends ModuleServiceProvider')
        ->and(File::get("{$this->sandbox}/bootstrap/providers.php"))
        ->toContain('App\Modules\Coupons\CouponsServiceProvider::class');
});

it('refuses a module that already exists', function (): void {
    $this->artisan('make:module', ['name' => 'Coupons'])->assertSuccessful();

    $this->artisan('make:module', ['name' => 'Coupons'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();
});

it('refuses names that are not StudlyCase letters', function (string $name): void {
    $this->artisan('make:module', ['name' => $name])->assertFailed();

    expect(File::isDirectory("{$this->sandbox}/app/Modules"))->toBeFalse();
})->with(['coupons', 'Coupon_Codes', 'Coupons2', '../Evil']);
