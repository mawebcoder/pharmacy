<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tests\Fixtures\Modules\Demo\DemoServiceProvider;

beforeEach(function (): void {
    $this->app->register(DemoServiceProvider::class);
});

it('derives the module name, alias, namespace and path from the provider', function (): void {
    $provider = new DemoServiceProvider($this->app);

    expect($provider->moduleName())->toBe('Demo')
        ->and($provider->moduleAlias())->toBe('demo')
        ->and($provider->moduleNamespace())->toBe('Tests\Fixtures\Modules\Demo')
        ->and($provider->modulePath('Routes/web.php'))->toBe(base_path('tests/Fixtures/Modules/Demo/Routes/web.php'));
});

it('serves the module route with its own view', function (): void {
    $this->get('/demo-module')
        ->assertOk()
        ->assertSee('ماژول نمونه بارگذاری شد');
});

it('registers the module Livewire components under the module alias', function (): void {
    Livewire::test('demo::greeting')
        ->assertSee('سلام مهمان')
        ->set('name', 'سارا')
        ->assertSee('سلام سارا');
});

it('loads the module migrations', function (): void {
    expect(app('migrator')->paths())->toContain(base_path('tests/Fixtures/Modules/Demo/Database/Migrations'));
});

it('registers the module console commands', function (): void {
    $this->artisan('demo:ping')
        ->expectsOutput('pong')
        ->assertSuccessful();
});
