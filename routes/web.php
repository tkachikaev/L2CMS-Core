<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminSessionController;
use App\Http\Controllers\Admin\AdministratorController as AdminAdministratorController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\NewsImageController as AdminNewsImageController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PageImageController as AdminPageImageController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\ThemeController as AdminThemeController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
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
use App\Http\Controllers\PageController;
use App\Http\Controllers\Localization\LocaleController;
use Illuminate\Support\Facades\Route;

$registerPublicRoutes = static function (bool $localized = false): void {
    $namePrefix = $localized ? 'localized.' : '';

    Route::get('/', HomeController::class)->name($namePrefix.'home');
    Route::get('/news', [NewsController::class, 'index'])->name($namePrefix.'news.index');

    if ($localized) {
        Route::get('/news/{slug}', [NewsController::class, 'showLocalized'])
            ->where('slug', '[^/]+')
            ->name($namePrefix.'news.show');
    } else {
        Route::get('/news/{news:slug}', [NewsController::class, 'show'])->name($namePrefix.'news.show');
    }

    if ($localized) {
        Route::get('/pages/{slug}', [PageController::class, 'showLocalized'])
            ->where('slug', '[^/]+')
            ->name($namePrefix.'pages.show');
    } else {
        Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name($namePrefix.'pages.show');
    }

    Route::view('/downloads', 'theme::pages.downloads')->name($namePrefix.'downloads');
    Route::view('/about', 'theme::pages.about')->name($namePrefix.'about');

    Route::middleware('guest')->group(function () use ($namePrefix): void {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name($namePrefix.'login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name($namePrefix.'login.store');

        Route::get('/register', [RegisteredUserController::class, 'create'])->name($namePrefix.'register');
        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name($namePrefix.'register.store');

        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name($namePrefix.'password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name($namePrefix.'password.email');

        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name($namePrefix.'password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name($namePrefix.'password.store');
    });

    Route::middleware(['auth', 'site.active'])->group(function () use ($namePrefix): void {
        Route::get('/email/verify', EmailVerificationPromptController::class)->name($namePrefix.'verification.notice');
        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name($namePrefix.'verification.verify');
        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name($namePrefix.'verification.send');

        Route::get('/account', AccountController::class)
            ->middleware('site.verified')
            ->name($namePrefix.'account');
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name($namePrefix.'logout');
    });
};

$registerPublicRoutes();

Route::prefix('{locale}')
    ->where(['locale' => (string) config('localization.locale_pattern')])
    ->group(static function () use ($registerPublicRoutes): void {
        $registerPublicRoutes(true);
    });

Route::get('/language/{locale}', [LocaleController::class, 'public'])
    ->where('locale', (string) config('localization.locale_pattern'))
    ->middleware('throttle:20,1')
    ->name('language.switch');

Route::prefix('admin')->name('admin.')->middleware('admin.headers')->group(function (): void {
    Route::post('/language/{locale}', [LocaleController::class, 'admin'])
        ->where('locale', (string) config('localization.locale_pattern'))
        ->middleware('throttle:20,1')
        ->name('language.switch');

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

        Route::get('/pages', [AdminPageController::class, 'index'])->name('pages.index');
        Route::get('/pages/create', [AdminPageController::class, 'create'])->name('pages.create');
        Route::match(['post', 'put'], '/pages/preview', [AdminPageController::class, 'preview'])
            ->middleware('throttle:20,1')
            ->name('pages.preview');
        Route::post('/pages', [AdminPageController::class, 'store'])->name('pages.store');
        Route::post('/pages/images', [AdminPageImageController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('pages.images.store');
        Route::get('/pages/{page}/edit', [AdminPageController::class, 'edit'])->name('pages.edit');
        Route::put('/pages/{page}', [AdminPageController::class, 'update'])->name('pages.update');
        Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])->name('pages.destroy');

        Route::get('/themes', [AdminThemeController::class, 'index'])->name('themes.index');
        Route::post('/themes/{theme}/activate', [AdminThemeController::class, 'activate'])
            ->where('theme', '[a-z0-9][a-z0-9_-]*')
            ->name('themes.activate');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::post('/users/{user}/verification', [AdminUserController::class, 'resendVerification'])
            ->middleware('throttle:6,1')
            ->name('users.verification');
        Route::post('/users/{user}/password-reset', [AdminUserController::class, 'sendPasswordReset'])
            ->middleware('throttle:5,1')
            ->name('users.password-reset');

        Route::get('/administrators', [AdminAdministratorController::class, 'index'])->name('administrators.index');
        Route::get('/administrators/create', [AdminAdministratorController::class, 'create'])->name('administrators.create');
        Route::post('/administrators', [AdminAdministratorController::class, 'store'])->name('administrators.store');
        Route::get('/administrators/{administrator}/edit', [AdminAdministratorController::class, 'edit'])->name('administrators.edit');
        Route::put('/administrators/{administrator}', [AdminAdministratorController::class, 'update'])->name('administrators.update');
        Route::put('/administrators/{administrator}/password', [AdminAdministratorController::class, 'updatePassword'])
            ->middleware('throttle:5,1')
            ->name('administrators.password');
        Route::patch('/administrators/{administrator}/status', [AdminAdministratorController::class, 'updateStatus'])->name('administrators.status');

        Route::get('/logs', [AdminAuditLogController::class, 'index'])->name('logs.index');
        Route::get('/logs/{auditLog}', [AdminAuditLogController::class, 'show'])->name('logs.show');

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
        Route::get('/settings/mail/custom', [AdminSettingsController::class, 'customMail'])
            ->name('settings.mail.custom');
        Route::post('/settings/mail/custom', [AdminSettingsController::class, 'sendCustomMail'])
            ->middleware('throttle:5,1')
            ->name('settings.mail.custom.send');
        Route::get('/settings/mail/templates/{template}', [AdminSettingsController::class, 'mailTemplate'])
            ->where('template', 'email_verification|password_reset|password_changed')
            ->name('settings.mail.template');
        Route::put('/settings/mail/templates/{template}', [AdminSettingsController::class, 'updateMailTemplate'])
            ->where('template', 'email_verification|password_reset|password_changed')
            ->name('settings.mail.template.update');
        Route::post('/settings/mail/templates/{template}/reset', [AdminSettingsController::class, 'resetMailTemplate'])
            ->where('template', 'email_verification|password_reset|password_changed')
            ->name('settings.mail.template.reset');
        Route::post('/settings/mail/templates/{template}/test', [AdminSettingsController::class, 'testMailTemplate'])
            ->where('template', 'email_verification|password_reset|password_changed')
            ->middleware('throttle:5,1')
            ->name('settings.mail.template.test');
        Route::get('/settings/system', [AdminSettingsController::class, 'system'])->name('settings.system');
        Route::get('/settings/languages', [AdminSettingsController::class, 'languages'])->name('settings.languages');
        Route::put('/settings/languages', [AdminSettingsController::class, 'updateLanguages'])->name('settings.languages.update');

        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
