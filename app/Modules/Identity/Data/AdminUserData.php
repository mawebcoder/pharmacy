<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Models\AdminUser;

/**
 * Input for creating or updating an admin user. A null password keeps the current one on update.
 */
final readonly class AdminUserData
{
    /**
     * @param  list<string>  $roles  Role names on the "admin" guard.
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public array $roles,
        public bool $isActive,
    ) {}

    /**
     * Keys are the admin_users columns (AdminUser::COLUMN_*) plus "roles".
     *
     * @param  array{name: string, email: string, password?: string|null, roles?: list<string>|null, is_active?: bool|int|string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data[AdminUser::COLUMN_NAME]),
            email: mb_strtolower(trim($data[AdminUser::COLUMN_EMAIL])),
            password: filled($data[AdminUser::COLUMN_PASSWORD] ?? null) ? (string) $data[AdminUser::COLUMN_PASSWORD] : null,
            roles: array_map(strval(...), $data['roles'] ?? []),
            isActive: (bool) ($data[AdminUser::COLUMN_IS_ACTIVE] ?? true),
        );
    }
}
