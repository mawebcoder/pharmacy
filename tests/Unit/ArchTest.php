<?php

declare(strict_types=1);

arch('application code uses strict types')
    ->expect(['App', 'Database'])
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

// config/*.php is not autoloaded, so this covers everything except the config files.
arch('env() is only read in config files')
    ->expect('env')
    ->not->toBeUsed();
