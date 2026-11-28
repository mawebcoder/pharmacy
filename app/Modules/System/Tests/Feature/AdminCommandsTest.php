<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\System\Enums\CommandRunStatus;
use App\Modules\System\Filament\Pages\CommandsPage;
use App\Modules\System\Jobs\RunAdminCommand;
use App\Modules\System\Models\CommandRun;
use App\Modules\System\Support\ArtisanCommandLine;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

/*
| «فرمان‌ها» (owner, 2026-11-28): super admins type an Artisan command; it runs in the background as the deploy user
| and its output is kept on the run.
*/
beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

it('is for super admins only', function (): void {
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create(), 'admin');
    livewire(CommandsPage::class)->assertForbidden();

    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');
    livewire(CommandsPage::class)->assertOk()->assertSee('php artisan');
});

it('queues the typed command on the commands queue, without "php artisan"', function (): void {
    Queue::fake();
    $admin = AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create();
    $this->actingAs($admin, 'admin');

    livewire(CommandsPage::class)
        ->fillForm(['command' => '  php artisan media:migrate-to-s3 --model=brands --dry-run '])
        ->call('run')
        ->assertHasNoFormErrors();

    $run = CommandRun::query()->sole();
    expect($run->getCommand())->toBe('media:migrate-to-s3 --model=brands --dry-run')
        ->and($run->getStatus())->toBe(CommandRunStatus::Queued)
        ->and($run->getAdminId())->toBe($admin->getKey())
        ->and($run->getAdminName())->toBe($admin->getAttribute('name'));
    Queue::assertPushedOn(RunAdminCommand::QUEUE, RunAdminCommand::class, fn (RunAdminCommand $job): bool => $job->runId === $run->getId());
});

it('refuses a command that does not exist, never ends or wipes the database', function (string $line): void {
    Queue::fake();
    $this->actingAs(AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create(), 'admin');

    livewire(CommandsPage::class)->fillForm(['command' => $line])->call('run')->assertHasFormErrors(['command']);

    expect(CommandRun::query()->exists())->toBeFalse();
    Queue::assertNotPushed(RunAdminCommand::class);
})->with(['no-such:command', 'tinker', 'queue:work', 'php artisan migrate:fresh --force', 'db:wipe']);

it('runs the command and keeps its exit code, output and system user', function (): void {
    $run = CommandRun::factory()->create([CommandRun::COLUMN_COMMAND => 'media:migrate-to-s3 --dry-run']);

    (new RunAdminCommand($run->getId()))->handle();

    $run->refresh();
    expect($run->getStatus())->toBe(CommandRunStatus::Done)
        ->and($run->getExitCode())->toBe(0)
        ->and($run->getOutput())->toContain('Would move 0 original(s)')
        ->and($run->getSystemUser())->toBe(posix_getpwuid(posix_geteuid())['name'])
        ->and($run->getFinishedAt())->not->toBeNull();
});

it('keeps a failing exit code', function (): void {
    $run = CommandRun::factory()->create([CommandRun::COLUMN_COMMAND => 'media:migrate-to-s3 --model=nothing']);

    (new RunAdminCommand($run->getId()))->handle();

    expect($run->fresh()->getStatus())->toBe(CommandRunStatus::Failed)
        ->and($run->fresh()->getExitCode())->toBe(2)
        ->and($run->fresh()->getOutput())->toContain('--model must be all');
});

it('refuses to run as any user but the configured one, or a refused command', function (): void {
    config(['app.commands_user' => 'deploy-user-that-is-not-me']);
    $wrongUser = CommandRun::factory()->create();
    (new RunAdminCommand($wrongUser->getId()))->handle();

    config(['app.commands_user' => null]);
    $wiping = CommandRun::factory()->create([CommandRun::COLUMN_COMMAND => 'db:wipe --force']);
    (new RunAdminCommand($wiping->getId()))->handle();

    expect($wrongUser->fresh()->getStatus())->toBe(CommandRunStatus::Failed)
        ->and($wrongUser->fresh()->getOutput())->toContain('deploy-user-that-is-not-me')
        ->and($wiping->fresh()->getStatus())->toBe(CommandRunStatus::Failed)
        ->and($wiping->fresh()->getExitCode())->toBeNull();
});

it('suggests commands as you type, never the refused ones', function (): void {
    $suggested = array_keys(ArtisanCommandLine::available());

    expect($suggested)->toContain('media:migrate-to-s3', 'search:reconcile')
        ->not->toContain('tinker', 'queue:work', 'db:wipe', 'migrate:fresh');
});
