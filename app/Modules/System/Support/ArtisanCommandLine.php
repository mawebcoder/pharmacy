<?php

declare(strict_types=1);

namespace App\Modules\System\Support;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\StringInput;
use Throwable;

/**
 * A command line typed in «فرمان‌ها» (owner, 2026-11-28): any Artisan command, written with or without "php artisan".
 * Refused: a command that does not exist, one that never ends (it would hold the deploy user's worker for the whole
 * run time), and one that wipes the database. Every run is non-interactive.
 */
final class ArtisanCommandLine
{
    /**
     * Commands that keep running until stopped, or wait for typing.
     */
    public const array NEVER_ENDING = ['tinker', 'serve', 'queue:work', 'queue:listen', 'horizon', 'horizon:work', 'schedule:work', 'pail', 'reverb:start', 'octane:start'];

    /**
     * Commands that delete every table or row.
     */
    public const array WIPING = ['db:wipe', 'migrate:fresh', 'migrate:reset', 'migrate:refresh'];

    /**
     * The line without "php artisan" and surrounding spaces: what is stored and run.
     */
    public static function normalize(string $line): string
    {
        return trim((string) preg_replace('/^\s*(php\s+)?artisan\s+/i', '', trim($line)));
    }

    /**
     * The command's name, or null when the line has none.
     */
    public static function name(string $line): ?string
    {
        try {
            $name = (new StringInput(self::normalize($line)))->getFirstArgument();
        } catch (Throwable) {
            return null;
        }

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Why the line cannot run, in Persian, or null when it can.
     */
    public static function refusal(string $line): ?string
    {
        $name = self::name($line);

        return match (true) {
            $name === null => 'فرمانی نوشته نشده است.',
            ! array_key_exists($name, Artisan::all()) => "فرمان «{$name}» وجود ندارد.",
            in_array($name, self::NEVER_ENDING, true) => "«{$name}» تمام نمی‌شود یا منتظر ورودی می‌ماند و از اینجا اجرا نمی‌شود.",
            in_array($name, self::WIPING, true) => "«{$name}» پایگاه داده را پاک می‌کند و از اینجا اجرا نمی‌شود.",
            default => null,
        };
    }

    /**
     * The commands offered as you type in «فرمان‌ها», by name, with their descriptions: hidden and refused ones left
     * out.
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        $commands = [];

        foreach (Artisan::all() as $name => $command) {
            if (! $command->isHidden() && ! in_array($name, [...self::NEVER_ENDING, ...self::WIPING], true)) {
                $commands[$name] = $command->getDescription();
            }
        }

        ksort($commands);

        return $commands;
    }

    /**
     * The line as it runs: never waiting for an answer.
     */
    public static function forRun(string $line): string
    {
        $line = self::normalize($line);

        return preg_match('/(^|\s)(--no-interaction|-n)(\s|$)/', $line) === 1 ? $line : $line.' --no-interaction';
    }
}
