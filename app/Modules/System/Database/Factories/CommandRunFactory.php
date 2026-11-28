<?php

declare(strict_types=1);

namespace App\Modules\System\Database\Factories;

use App\Modules\System\Enums\CommandRunStatus;
use App\Modules\System\Models\CommandRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommandRun>
 */
final class CommandRunFactory extends Factory
{
    protected $model = CommandRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            CommandRun::COLUMN_COMMAND => 'about --only=environment',
            CommandRun::COLUMN_STATUS => CommandRunStatus::Queued,
        ];
    }
}
