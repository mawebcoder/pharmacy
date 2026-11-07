<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests (tests/Feature and each module's Tests/Feature) boot the
| application and run against the MySQL test database ("pharmacy_testing"),
| wrapped in a transaction per test.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../app/Modules/*/Tests/Feature');

// testImage() and testSvg(): real image files for media upload tests.
require_once __DIR__.'/../app/Modules/Media/Tests/helpers.php';
