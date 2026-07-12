<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminSessionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\NewsImageController as AdminNewsImageController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\ThemeController as AdminThemeController;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{news:slug}', [NewsController::class, 'show'])->name('news.show');
Route::view('/downloads', 'theme::pages.downloads')->name('downloads');
Route::view('/about', 'theme::pages.about')->name('about');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/account', AccountController::class)
        ->middleware('site.verified')
        ->name('account');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

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
        Route::post('/news/preview', [AdminNewsController::class, 'preview'])
            ->middleware('throttle:20,1')
            ->name('news.preview');
        Route::post('/news', [AdminNewsController::class, 'store'])->name('news.store');
        Route::post('/news/images', [AdminNewsImageController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('news.images.store');
        Route::get('/news/{news}/edit', [AdminNewsController::class, 'edit'])->name('news.edit');
        Route::put('/news/{news}', [AdminNewsController::class, 'update'])->name('news.update');
        Route::delete('/news/{news}', [AdminNewsController::class, 'destroy'])->name('news.destroy');

        Route::get('/themes', [AdminThemeController::class, 'index'])->name('themes.index');
        Route::post('/themes/{theme}/activate', [AdminThemeController::class, 'activate'])
            ->where('theme', '[a-z0-9][a-z0-9_-]*')
            ->name('themes.activate');

        Route::get('/settings', [AdminSettingsController::class, 'general'])->name('settings.general');
        Route::put('/settings', [AdminSettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::get('/settings/game-server', [AdminSettingsController::class, 'gameServer'])->name('settings.game-server');
        Route::post('/settings/game-server', [AdminSettingsController::class, 'storeGameServer'])->name('settings.game-server.store');
        Route::put('/settings/game-server/{gameServer}', [AdminSettingsController::class, 'updateGameServer'])->name('settings.game-server.update');
        Route::delete('/settings/game-server/{gameServer}', [AdminSettingsController::class, 'destroyGameServer'])->name('settings.game-server.destroy');
        Route::get('/settings/login-server', [AdminSettingsController::class, 'loginServer'])->name('settings.login-server');
        Route::get('/settings/registration', [AdminSettingsController::class, 'registration'])->name('settings.registration');
        Route::put('/settings/registration', [AdminSettingsController::class, 'updateRegistration'])->name('settings.registration.update');
        Route::get('/settings/mail', [AdminSettingsController::class, 'mail'])->name('settings.mail');
        Route::put('/settings/mail', [AdminSettingsController::class, 'updateMail'])->name('settings.mail.update');
        Route::post('/settings/mail/test', [AdminSettingsController::class, 'testMail'])
            ->middleware('throttle:5,1')
            ->name('settings.mail.test');

        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
