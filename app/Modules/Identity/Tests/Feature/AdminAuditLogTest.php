<?php

declare(strict_types=1);

use App\Modules\Identity\Actions\ResetAdminTwoFactor;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\System\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Modules\System\Filament\Resources\ActivityLogs\Schemas\ActivityLogInfolist;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

it('logs admin account changes with who made them and never the password', function (): void {
    $actor = AdminUser::factory()->superAdmin()->create();
    $admin = AdminUser::factory()->create(['is_active' => true]);
    $this->actingAs($actor, 'admin');

    $admin->update(['is_active' => false, 'password' => 'Another-Secret-1405!']);

    $activity = Activity::query()->where('event', 'updated')->whereMorphedTo('subject', $admin)->latest('id')->first();
    expect($activity->causer->is($actor))->toBeTrue()
        ->and($activity->attribute_changes->toArray())->toEqual(['attributes' => ['is_active' => false], 'old' => ['is_active' => true]])
        ->and(json_encode($activity->toArray()))->not->toContain('password')->not->toContain('Another-Secret');
});

it('logs role assignments', function (): void {
    $admin = AdminUser::factory()->create();

    $admin->assignRole(Role::findOrCreate(AdminRole::Operator->value, 'admin'));

    $activity = Activity::query()->where('event', 'roles_attached')->whereMorphedTo('subject', $admin)->first();
    expect($activity?->properties->get('roles'))->toBe(['operator']);
});

it('logs a two-factor reset', function (): void {
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->create();

    app(ResetAdminTwoFactor::class)($admin);

    expect(Activity::query()->where('event', 'two_factor_reset')->whereMorphedTo('subject', $admin)->exists())->toBeTrue();
});

it('shows activity in Persian on the activity log page', function (): void {
    Filament\Facades\Filament::setCurrentPanel('admin');
    $actor = AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(['name' => 'مدیر سیستم']);
    $this->actingAs($actor, 'admin');
    $admin = AdminUser::factory()->create(['name' => 'سارا']);

    $admin->update(['is_active' => false]);

    Pest\Livewire\livewire(ListActivityLogs::class)
        ->assertSee(['ویرایش', 'مدیر: سارا', 'فعال', 'مدیر سیستم']);
});

it('opens an activity with its changes for super admins only', function (): void {
    $superAdmin = AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create();
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();
    $admin = AdminUser::factory()->create();
    $admin->update(['is_active' => false]);
    $activity = Activity::query()->where('event', 'updated')->whereMorphedTo('subject', $admin)->firstOrFail();

    $this->actingAs($superAdmin, 'admin')->get("/admin/activity-logs/{$activity->id}")
        ->assertOk()
        ->assertSee('prettyjson', false)
        ->assertSee('مقادیر قبلی (old) و جدید (attributes)');
    $this->actingAs($operator, 'admin')->get("/admin/activity-logs/{$activity->id}")->assertForbidden();
});

it('formats activity changes as JSON that keeps Persian text readable', function (): void {
    $json = ActivityLogInfolist::json(collect(['roles' => ['مدیر ارشد'], 'url' => '/admin/x']));

    expect($json)->toBe('{"roles":["مدیر ارشد"],"url":"/admin/x"}');
});
