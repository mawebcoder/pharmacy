<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Whether files on a disk are public, from its own config (owner, 2026-11-28): public disks (the s3 bucket behind the
 * CDN, the local "media" disk) get plain links at their address; any other disk gets signed, expiring links.
 */
final class DiskVisibility
{
    public static function of(string $disk): string
    {
        return config("filesystems.disks.{$disk}.visibility") === 'public' ? 'public' : 'private';
    }

    public static function isPublic(string $disk): bool
    {
        return self::of($disk) === 'public';
    }
}
