<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminSessionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\NewsImageController as AdminNewsImageController;
use App\Http\Controllers\Admin\ThemeController as AdminThemeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{news:slug}', [NewsController::class, 'show'])->name('news.show');

Route::view('/login', 'theme::auth.login')->name('login');
Route::view('/register', 'theme::auth.register')->name('register');
Route::view('/downloads', 'theme::pages.downloads')->name('downloads');
Route::view('/about', 'theme::pages.about')->name('about');

Route::prefix('admin')->name('admin.')->middleware('admin.headers')->group(function (): void {
    Route::middleware('admin.guest')->group(function (): void {
        Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware('admin.auth')->group(function (): void {
        Route::get('', AdminDashboardController::class)->name('dashboard');
        Route::redirect('/dashboard', '/admin');

        Route::get('/news', [AdminNewsController::class, 'index'])->name('news.index');
        Route::get('/news/create', [AdminNewsController::class, 'create'])->name('news.create');
        Route::post('/news', [AdminNewsController::class, 'store'])->name('news.store');
        Route::post('/news/images', [AdminNewsImageController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('news.images.store');
        Route::get('/news/{news}/edit', [AdminNewsController::class, 'edit'])->name('news.edit');
        Route::put('/news/{news}', [AdminNewsController::class, 'update'])->name('news.update');

        Route::get('/themes', [AdminThemeController::class, 'index'])->name('themes.index');
        Route::post('/themes/{theme}/activate', [AdminThemeController::class, 'activate'])
            ->where('theme', '[a-z0-9][a-z0-9_-]*')
            ->name('themes.activate');

        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
