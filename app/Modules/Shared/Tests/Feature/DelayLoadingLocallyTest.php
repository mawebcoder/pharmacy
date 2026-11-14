<?php

declare(strict_types=1);

it('holds back only Livewire requests, and only on the local environment', function (string $env, array $headers, bool $delayed): void {
    config(['app.local_loading_delay_ms' => 300]);
    $this->app['env'] = $env;

    $started = microtime(true);
    $this->withHeaders($headers)->get('/login')->assertOk();
    $elapsed = microtime(true) - $started;

    expect($elapsed >= 0.3)->toBe($delayed);
})->with([
    'local page change' => ['local', ['X-Livewire-Navigate' => '1'], true],
    'local component update' => ['local', ['X-Livewire' => '1'], true],
    'local full page load' => ['local', [], false],
    'production page change' => ['production', ['X-Livewire-Navigate' => '1'], false],
]);
