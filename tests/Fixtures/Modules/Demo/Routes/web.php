<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/demo-module', fn (): View => view('demo::hello'))->name('demo.hello');
