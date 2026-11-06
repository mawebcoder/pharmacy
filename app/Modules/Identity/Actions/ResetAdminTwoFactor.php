<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\AdminUser;

/**
 * Clears a staff member's authenticator app and recovery codes; they must set up 2FA again at their next sign-in.
 */
final class ResetAdminTwoFactor
{
    public function __invoke(AdminUser $admin): void
    {
        $admin->saveAppAuthenticationSecret(null);
        $admin->saveAppAuthenticationRecoveryCodes(null);

        activity('admin')
            ->performedOn($admin)
            ->event('two_factor_reset')
            ->log('two_factor_reset');
    }
}
