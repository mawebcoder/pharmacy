<?php

declare(strict_types=1);

use App\Modules\Seo\Http\Controllers\RobotsController;
use App\Modules\Seo\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Sitemaps and robots (roadmap S38). public/robots.txt is gone, so Nginx passes /robots.txt to Laravel.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('seo.sitemap');
Route::get('/sitemaps/products-{file}.xml', [SitemapController::class, 'products'])->whereNumber('file')->name('seo.sitemap.products');
Route::get('/sitemaps/{section}.xml', [SitemapController::class, 'section'])->whereAlpha('section')->name('seo.sitemap.section');
Route::get('/robots.txt', RobotsController::class)->name('seo.robots');
