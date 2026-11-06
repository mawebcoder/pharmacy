<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Data\AdminUserData;
use App\Modules\Identity\Models\AdminUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation for creating or updating an admin user (roadmap C5). The Filament form applies the same baseRules().
 */
final class StoreAdminUserRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|ValidationRule|Unique|Exists|Password>>
     */
    public static function baseRules(?int $adminUserId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admin_users', 'email')->ignore($adminUserId)],
            'password' => [$adminUserId === null ? 'required' : 'nullable', 'string', Password::default()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
            'is_active' => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        $admin = $this->user('admin');
        $target = $this->route('adminUser');

        return $admin instanceof AdminUser
            && ($target instanceof AdminUser ? $admin->can('update', $target) : $admin->can('create', AdminUser::class));
    }

    /**
     * @return array<string, array<int, string|ValidationRule|Unique|Exists|Password>>
     */
    public function rules(): array
    {
        $target = $this->route('adminUser');

        return self::baseRules($target instanceof AdminUser ? $target->id : null);
    }

    public function toDto(): AdminUserData
    {
        /** @var array{name: string, email: string, password?: string|null, roles?: list<string>, is_active?: bool} $validated */
        $validated = $this->validated();

        return AdminUserData::fromArray($validated);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
