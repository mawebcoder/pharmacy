<?php

declare(strict_types=1);

namespace App\Modules\Profile;

use App\Modules\Shared\ModuleServiceProvider;

/**
 * The customer's account area («حساب من», roadmap B9.9; its own module since 2026-11-18): the account pages, their
 * routes under /account and the account frame. Data comes from the modules that own it (Identity, Orders, Catalog)
 * through their queries, actions and components.
 */
final class ProfileServiceProvider extends ModuleServiceProvider
{
    //
}
