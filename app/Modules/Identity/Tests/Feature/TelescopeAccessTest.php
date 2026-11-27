<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;

it('keeps Telescope closed while TELESCOPE_ENABLED is off', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin')
        ->get('/telescope/requests')
        ->assertNotFound();
});

describe('when enabled', function (): void {
    beforeEach(function (): void {
        config(['telescope.enabled' => true]);
        (new Laravel\Telescope\TelescopeServiceProvider($this->app))->boot();
        (new TelescopeServiceProvider($this->app))->boot();
    });

    it('lets an active super admin with two-factor authentication open Telescope', function (): void {
        $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin')
            ->get('/telescope/requests')
            ->assertOk()
            ->assertSee('Telescope');
    });

    it('refuses Telescope to everyone but active super admins with two-factor authentication', function (Closure $signIn): void {
        $signIn($this);

        $this->get('/telescope/requests')->assertForbidden();
    })->with([
        'guest' => fn (): Closure => fn (): null => null,
        'super admin without two-factor authentication' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->superAdmin()->create(), 'admin'),
        'inactive super admin' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->inactive()->create(), 'admin'),
        'operator with two-factor authentication' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin'),
        'content editor with two-factor authentication' => fn (): Closure => fn ($test) => $test->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::ContentEditor)->create(), 'admin'),
        'customer' => fn (): Closure => fn ($test) => $test->actingAs(User::factory()->create(), 'web'),
    ]);

    it('prunes entries older than 72 hours every night', function (): void {
        $prune = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains((string) $event->command, 'telescope:prune'));

        expect($prune?->command)->toContain('--hours=72');
    });
});

describe('outside local', function (): void {
    it('runs only the watchers that can see an error or a slow query', function (): void {
        expect(array_keys(config('telescope.watchers')))->toEqualCanonicalizing(TelescopeServiceProvider::ERROR_WATCHERS);
    });

    it('keeps failed requests, failed jobs and slow queries, and drops the rest', function (IncomingEntry $entry, bool $kept): void {
        expect(TelescopeServiceProvider::isError($entry))->toBe($kept);
    })->with([
        'failed request' => fn (): array => [IncomingEntry::make(['response_status' => 500])->type(EntryType::REQUEST), true],
        'successful request' => fn (): array => [IncomingEntry::make(['response_status' => 200])->type(EntryType::REQUEST), false],
        'failed job' => fn (): array => [IncomingEntry::make(['status' => 'failed'])->type(EntryType::JOB), true],
        'processed job' => fn (): array => [IncomingEntry::make(['status' => 'processed'])->type(EntryType::JOB), false],
        'slow query' => fn (): array => [IncomingEntry::make(['slow' => true])->type(EntryType::QUERY), true],
        'fast query' => fn (): array => [IncomingEntry::make(['slow' => false])->type(EntryType::QUERY), false],
        'cache hit' => fn (): array => [IncomingEntry::make([])->type(EntryType::CACHE), false],
    ]);
});
