<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

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
     * @param  array{name: string, email: string, password?: string|null, roles?: list<string>|null, is_active?: bool|int|string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            email: mb_strtolower(trim($data['email'])),
            password: filled($data['password'] ?? null) ? (string) $data['password'] : null,
            roles: array_map(strval(...), $data['roles'] ?? []),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
