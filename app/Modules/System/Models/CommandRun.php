<?php

declare(strict_types=1);

namespace App\Modules\System\Models;

use App\Modules\System\Database\Factories\CommandRunFactory;
use App\Modules\System\Enums\CommandRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One Artisan command run from «فرمان‌ها» (owner, 2026-11-28): the command line (without "php artisan"), who ran it,
 * the system user it ran as, and its exit code and output. A log itself, so not audited.
 */
final class CommandRun extends Model
{
    /** @use HasFactory<CommandRunFactory> */
    use HasFactory;

    public const string COLUMN_ID = 'id';

    public const string COLUMN_COMMAND = 'command';

    public const string COLUMN_STATUS = 'status';

    public const string COLUMN_EXIT_CODE = 'exit_code';

    public const string COLUMN_OUTPUT = 'output';

    public const string COLUMN_SYSTEM_USER = 'system_user';

    public const string COLUMN_ADMIN_ID = 'admin_id';

    public const string COLUMN_ADMIN_NAME = 'admin_name';

    public const string COLUMN_STARTED_AT = 'started_at';

    public const string COLUMN_FINISHED_AT = 'finished_at';

    public const string COLUMN_CREATED_AT = 'created_at';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getCommand(): string
    {
        return (string) $this->getAttribute(self::COLUMN_COMMAND);
    }

    public function setCommand(string $command): static
    {
        return $this->setAttribute(self::COLUMN_COMMAND, $command);
    }

    public function getStatus(): CommandRunStatus
    {
        return $this->getAttribute(self::COLUMN_STATUS);
    }

    public function setStatus(CommandRunStatus $status): static
    {
        return $this->setAttribute(self::COLUMN_STATUS, $status);
    }

    public function getExitCode(): ?int
    {
        $code = $this->getAttribute(self::COLUMN_EXIT_CODE);

        return $code === null ? null : (int) $code;
    }

    public function setExitCode(?int $exitCode): static
    {
        return $this->setAttribute(self::COLUMN_EXIT_CODE, $exitCode);
    }

    public function getOutput(): ?string
    {
        return $this->getAttribute(self::COLUMN_OUTPUT);
    }

    public function setOutput(?string $output): static
    {
        return $this->setAttribute(self::COLUMN_OUTPUT, $output);
    }

    public function getSystemUser(): ?string
    {
        return $this->getAttribute(self::COLUMN_SYSTEM_USER);
    }

    public function setSystemUser(?string $systemUser): static
    {
        return $this->setAttribute(self::COLUMN_SYSTEM_USER, $systemUser);
    }

    public function getAdminId(): ?int
    {
        $adminId = $this->getAttribute(self::COLUMN_ADMIN_ID);

        return $adminId === null ? null : (int) $adminId;
    }

    public function setAdminId(?int $adminId): static
    {
        return $this->setAttribute(self::COLUMN_ADMIN_ID, $adminId);
    }

    public function getAdminName(): ?string
    {
        return $this->getAttribute(self::COLUMN_ADMIN_NAME);
    }

    public function setAdminName(?string $adminName): static
    {
        return $this->setAttribute(self::COLUMN_ADMIN_NAME, $adminName);
    }

    public function getStartedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_STARTED_AT);
    }

    public function setStartedAt(?Carbon $startedAt): static
    {
        return $this->setAttribute(self::COLUMN_STARTED_AT, $startedAt);
    }

    public function getFinishedAt(): ?Carbon
    {
        return $this->getAttribute(self::COLUMN_FINISHED_AT);
    }

    public function setFinishedAt(?Carbon $finishedAt): static
    {
        return $this->setAttribute(self::COLUMN_FINISHED_AT, $finishedAt);
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            self::COLUMN_STATUS => CommandRunStatus::class,
            self::COLUMN_EXIT_CODE => 'integer',
            self::COLUMN_STARTED_AT => 'datetime',
            self::COLUMN_FINISHED_AT => 'datetime',
        ];
    }
}
