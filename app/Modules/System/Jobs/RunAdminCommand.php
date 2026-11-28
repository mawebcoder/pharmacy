<?php

declare(strict_types=1);

namespace App\Modules\System\Jobs;

use App\Modules\System\Enums\CommandRunStatus;
use App\Modules\System\Models\CommandRun;
use App\Modules\System\Support\ArtisanCommandLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Runs one command line from «فرمان‌ها» on the "commands" queue, which only the deploy user's worker takes (owner,
 * 2026-11-28; deploy/supervisor/hutana.conf). With config('app.commands_user') set, a worker running as anyone else
 * refuses the run. Never retried: a command that stopped half-way is run again by hand.
 */
final class RunAdminCommand implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const string QUEUE = 'commands';

    /** Output kept on the run, from the end (the summary is last). */
    public const int MAX_OUTPUT_BYTES = 1_000_000;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(): void
    {
        $run = CommandRun::query()->find($this->runId);

        if ($run === null || $run->getStatus() !== CommandRunStatus::Queued) {
            return;
        }

        $systemUser = self::systemUser();
        $run->setSystemUser($systemUser)->setStatus(CommandRunStatus::Running)->setStartedAt(now())->save();
        $requiredUser = (string) config('app.commands_user');

        if ($requiredUser !== '' && $systemUser !== $requiredUser) {
            $this->finish($run, null, "اجرا نشد: فرمان‌ها فقط با کاربر {$requiredUser} اجرا می‌شوند، این worker با کاربر {$systemUser} است.");

            return;
        }

        $refusal = ArtisanCommandLine::refusal($run->getCommand());

        if ($refusal !== null) {
            $this->finish($run, null, 'اجرا نشد: '.$refusal);

            return;
        }

        $output = new BufferedOutput;

        try {
            $exitCode = Artisan::call(ArtisanCommandLine::forRun($run->getCommand()), [], $output);
        } catch (Throwable $exception) {
            $exitCode = 1;
            $output->writeln($exception::class.': '.$exception->getMessage());
        }

        $this->finish($run, $exitCode, $output->fetch());
    }

    public function failed(?Throwable $exception): void
    {
        $run = CommandRun::query()->find($this->runId);

        if ($run !== null && $run->getStatus()->isActive()) {
            $this->finish($run, null, 'متوقف شد: '.($exception?->getMessage() ?? 'زمان اجرا تمام شد.'));
        }
    }

    private function finish(CommandRun $run, ?int $exitCode, string $output): void
    {
        if (strlen($output) > self::MAX_OUTPUT_BYTES) {
            $output = "…\n".mb_strcut($output, strlen($output) - self::MAX_OUTPUT_BYTES);
        }

        $run->setExitCode($exitCode)
            ->setOutput($output)
            ->setStatus($exitCode === 0 ? CommandRunStatus::Done : CommandRunStatus::Failed)
            ->setFinishedAt(now())
            ->save();
    }

    private static function systemUser(): string
    {
        return (string) (posix_getpwuid(posix_geteuid())['name'] ?? getenv('USER') ?: 'unknown');
    }
}
