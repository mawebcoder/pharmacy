<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\Demo;

use App\Modules\Shared\ModuleServiceProvider;

/**
 * Test-only module, registered inside ModuleServiceProviderTest to prove a module's parts are loaded.
 */
final class DemoServiceProvider extends ModuleServiceProvider
{
    //
}
