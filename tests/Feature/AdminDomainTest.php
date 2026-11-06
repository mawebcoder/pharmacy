<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;

function adminUrl(string $path = ''): string
{
    return 'http://'.config('app.admin_domain').'/'.ltrim($path, '/');
}

it('sends guests to the login page on the admin subdomain', function (): void {
    $this->get(adminUrl())->assertRedirect(adminUrl('login'));

    $this->get(adminUrl('login'))->assertOk();
});

it('shows admins the dashboard at the subdomain root', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(adminUrl())
        ->assertOk();
});

it('forbids customers from opening the admin panel', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(adminUrl())
        ->assertForbidden();
});

it('lets only admins view Horizon', function (): void {
    expect(Gate::forUser(User::factory()->admin()->create())->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
});

it('does not allow the admin flag to be mass assigned', function (): void {
    $user = User::create([
        'name' => 'مشتری',
        'email' => 'customer@example.com',
        'password' => 'secret-password',
        'is_admin' => true,
    ]);

    expect($user->fresh()->is_admin)->toBeFalse();
});

it('does not serve the admin panel from the storefront domain', function (): void {
    $this->get('http://localhost/admin')->assertNotFound();
    $this->get('http://localhost/admin/login')->assertNotFound();
    $this->get('http://localhost/horizon')->assertNotFound();
});
