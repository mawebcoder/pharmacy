<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The home page, built from the admin's widgets (roadmap B5, B9.2, S13b).
Route::livewire('/', 'content::home-page')->name('home');

// Static pages written in the admin («صفحه‌های ثابت», roadmap B5, S34).
Route::livewire('/pages/{slug}', 'content::static-page')->name('pages.show');

// Frequently asked questions and the contact form (roadmap B5, S34).
Route::livewire('/faq', 'content::faq-page')->name('faq');
Route::livewire('/contact', 'content::contact-page')->name('contact');
