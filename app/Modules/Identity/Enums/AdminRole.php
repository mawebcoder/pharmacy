<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Staff roles on the "admin" guard (roadmap D3.6). super_admin passes every permission check through Shield.
 */
enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Operator = 'operator';
    case ContentEditor = 'content_editor';

    /**
     * Persian label for a stored role name; roles created later in the admin show their own name.
     */
    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }

    /**
     * Built-in roles are referenced in code, so they cannot be renamed or deleted.
     */
    public static function isBuiltIn(string $name): bool
    {
        return self::tryFrom($name) instanceof self;
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدیر ارشد',
            self::Operator => 'اپراتور',
            self::ContentEditor => 'ویراستار محتوا',
        };
    }
}
