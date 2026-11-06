<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminDomainTest extends TestCase
{
    use RefreshDatabase;

    private function adminUrl(string $path = ''): string
    {
        return 'http://'.config('app.admin_domain').'/'.ltrim($path, '/');
    }

    public function test_guests_are_sent_to_the_login_page_on_the_admin_subdomain(): void
    {
        $this->get($this->adminUrl())->assertRedirect($this->adminUrl('login'));

        $this->get($this->adminUrl('login'))->assertOk();
    }

    public function test_admins_see_the_dashboard_at_the_subdomain_root(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get($this->adminUrl())
            ->assertOk();
    }

    public function test_customers_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->adminUrl())
            ->assertForbidden();
    }

    public function test_only_admins_may_view_horizon(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser(null)->allows('viewHorizon'));
    }

    public function test_the_admin_flag_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'مشتری',
            'email' => 'customer@example.com',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_the_admin_panel_is_not_reachable_from_the_storefront_domain(): void
    {
        $this->get('http://localhost/admin')->assertNotFound();
        $this->get('http://localhost/admin/login')->assertNotFound();
        $this->get('http://localhost/horizon')->assertNotFound();
    }
}
