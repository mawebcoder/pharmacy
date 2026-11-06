<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\Demo\Console;

use Illuminate\Console\Command;

final class DemoPingCommand extends Command
{
    protected $signature = 'demo:ping';

    protected $description = 'Test-only command of the Demo module';

    public function handle(): int
    {
        $this->line('pong');

        return self::SUCCESS;
    }
}
