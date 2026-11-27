<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

it('redirects a plain-HTTP page request to the same URL on HTTPS with 301', function (): void {
    config(['app.force_https' => true]);

    $this->get('http://localhost/up?ref=sms')
        ->assertStatus(301)
        ->assertRedirect('https://localhost/up?ref=sms');
});

it('redirects a plain-HTTP POST with 308 so the method and body are kept', function (): void {
    config(['app.force_https' => true]);

    $this->post('http://localhost/webhooks/tipax', ['status' => 'delivered'])
        ->assertStatus(308)
        ->assertRedirect('https://localhost/webhooks/tipax');
});

it('does not redirect a request that reached the CDN over HTTPS', function (): void {
    config(['app.force_https' => true]);

    $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://localhost/up')
        ->assertOk();
});

it('does not redirect when forcing HTTPS is off', function (): void {
    config(['app.force_https' => false]);

    $this->get('http://localhost/up')->assertOk();
});

it('builds every link on APP_URL\'s domain, also for a request sent straight to the server\'s IP', function (): void {
    config(['app.force_https' => true, 'app.url' => 'https://hutana.ir']);
    $this->app->getProvider(AppServiceProvider::class)?->boot();

    $this->get('https://185.221.237.81/up')->assertOk();

    expect(route('brands.index'))->toBe('https://hutana.ir/brands');
});
