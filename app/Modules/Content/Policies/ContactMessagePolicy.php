<?php

declare(strict_types=1);

namespace App\Modules\Content\Policies;

use App\Modules\Content\Models\ContactMessage;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Messages from /contact (roadmap S34): read and marked answered; nobody creates, edits or deletes them in the admin.
 */
final class ContactMessagePolicy
{
    public function viewAny(Authorizable $admin): bool
    {
        return $admin->can('ViewAny:ContactMessage');
    }

    public function view(Authorizable $admin, ContactMessage $message): bool
    {
        return $admin->can('View:ContactMessage');
    }

    public function create(Authorizable $admin): bool
    {
        return false;
    }

    public function update(Authorizable $admin, ContactMessage $message): bool
    {
        return $admin->can('Update:ContactMessage');
    }

    public function delete(Authorizable $admin, ContactMessage $message): bool
    {
        return false;
    }
}
