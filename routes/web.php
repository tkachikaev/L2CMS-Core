<?php

use App\Http\Controllers\Account\GameAccountController;
use App\Http\Controllers\Account\GameAccountPasswordController;
use App\Http\Controllers\Admin\AccountSecurityController as AdminAccountSecurityController;
use App\Http\Controllers\Admin\AdministratorController as AdminAdministratorController;
use App\Http\Controllers\Admin\AdministratorTwoFactorController as AdminAdministratorTwoFactorController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminSessionController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController as AdminTwoFactorChallengeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GameAccountSettingsController as AdminGameAccountSettingsController;
use App\Http\Controllers\Admin\GameServerConnectionController as AdminGameServerConnectionController;
use App\Http\Controllers\Admin\GameServerController as AdminGameServerController;
use App\Http\Controllers\Admin\LoginServerController as AdminLoginServerController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\NewsImageController as AdminNewsImageController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PageImageController as AdminPageImageController;
use App\Http\Controllers\Admin\SecuritySettingsController as AdminSecuritySettingsController;
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
use App\Http\Controllers\Localization\LocaleController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ServerMonitorStatusController;
use App\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

$registerPublicRoutes = static function (bool $localized = false): void {
    $namePrefix = $localized ? 'localized.' : '';

    Route::get('/', HomeController::class)->name($namePrefix.'home');
    Route::post('/server-status/refresh', ServerMonitorStatusController::class)
        ->middleware('throttle:120,1')
        ->name($namePrefix.'server-monitor.refresh');
    Route::get('/news', [NewsController::class, 'index'])->name($namePrefix.'news.index');
    Route::get('/statistics', [StatisticsController::class, $localized ? 'indexLocalized' : 'index'])
        ->middleware('throttle:120,1')
        ->name($namePrefix.'statistics.index');
    Route::get('/statistics/{gameServer}', [StatisticsController::class, $localized ? 'showLocalized' : 'show'])
        ->whereNumber('gameServer')
        ->middleware('throttle:120,1')
        ->name($namePrefix.'statistics.show');

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

        Route::middleware('site.verified')->group(function () use ($namePrefix): void {
            Route::get('/account', AccountController::class)->name($namePrefix.'account');
            Route::get('/account/game-accounts/create', [GameAccountController::class, 'create'])
                ->name($namePrefix.'game-accounts.create');
            Route::post('/account/game-accounts', [GameAccountController::class, 'store'])
                ->middleware('throttle:game-account-create')
                ->name($namePrefix.'game-accounts.store');
            Route::get('/account/game-accounts/{gameAccount}', [GameAccountController::class, 'show'])
                ->whereNumber('gameAccount')
                ->name($namePrefix.'game-accounts.show');
            Route::put('/account/game-accounts/{gameAccount}/password', [GameAccountPasswordController::class, 'update'])
                ->whereNumber('gameAccount')
                ->middleware('throttle:game-account-password')
                ->name($namePrefix.'game-accounts.password');
        });
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
        Route::post('/login', [AdminSessionController::class, 'store'])
            ->middleware('throttle:admin-login-ip')
            ->name('login.store');

        Route::get('/two-factor-challenge', [AdminTwoFactorChallengeController::class, 'create'])
            ->name('two-factor.challenge');
        Route::post('/two-factor-challenge', [AdminTwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:admin-two-factor-challenge')
            ->name('two-factor.challenge.store');
        Route::post('/two-factor-challenge/cancel', [AdminTwoFactorChallengeController::class, 'destroy'])
            ->name('two-factor.challenge.cancel');
    });

    Route::middleware('admin.auth')->group(function (): void {
        Route::get('', AdminDashboardController::class)->name('dashboard');
        Route::post('/server-monitor/status', [AdminDashboardController::class, 'status'])
            ->middleware('throttle:120,1')
            ->name('server-monitor.status');
        Route::post('/server-monitor/refresh', [AdminDashboardController::class, 'refresh'])
            ->middleware('throttle:6,1')
            ->name('server-monitor.refresh');
        Route::redirect('/dashboard', '/admin');

        Route::get('/account/security', [AdminAccountSecurityController::class, 'show'])->name('account.security');
        Route::post('/account/security/two-factor/setup', [AdminAccountSecurityController::class, 'begin'])
            ->middleware('throttle:5,1')
            ->name('account.two-factor.setup');
        Route::post('/account/security/two-factor/confirm', [AdminAccountSecurityController::class, 'confirm'])
            ->middleware('throttle:5,1')
            ->name('account.two-factor.confirm');
        Route::post('/account/security/two-factor/recovery-codes', [AdminAccountSecurityController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:3,1')
            ->name('account.two-factor.recovery-codes');
        Route::delete('/account/security/two-factor', [AdminAccountSecurityController::class, 'disable'])
            ->middleware('throttle:3,1')
            ->name('account.two-factor.disable');

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
        Route::delete('/administrators/{administrator}/two-factor', [AdminAdministratorTwoFactorController::class, 'destroy'])
            ->middleware('throttle:3,1')
            ->name('administrators.two-factor.destroy');

        Route::get('/logs', [AdminAuditLogController::class, 'index'])->name('logs.index');
        Route::get('/logs/{auditLog}', [AdminAuditLogController::class, 'show'])->name('logs.show');

        Route::get('/settings', [AdminSettingsController::class, 'general'])->name('settings.general');
        Route::put('/settings', [AdminSettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::get('/settings/game-server', [AdminGameServerController::class, 'index'])->name('settings.game-server');
        Route::post('/settings/game-server/{gameServer}/connection', [AdminGameServerConnectionController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('settings.game-server.connection');
        Route::get('/settings/login-server', [AdminLoginServerController::class, 'index'])->name('settings.login-server');
        Route::post('/settings/login-server', [AdminLoginServerController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('settings.login-server.store');
        Route::post('/settings/login-server/{loginServer}', [AdminLoginServerController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('settings.login-server.update');
        Route::delete('/settings/login-server/{loginServer}', [AdminLoginServerController::class, 'destroy'])
            ->name('settings.login-server.destroy');
        Route::get('/settings/registration', [AdminSettingsController::class, 'registration'])->name('settings.registration');
        Route::put('/settings/registration', [AdminSettingsController::class, 'updateRegistration'])->name('settings.registration.update');
        Route::get('/settings/game-accounts', [AdminGameAccountSettingsController::class, 'index'])
            ->name('settings.game-accounts');
        Route::put('/settings/game-accounts', [AdminGameAccountSettingsController::class, 'update'])
            ->name('settings.game-accounts.update');
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
        Route::get('/settings/security', [AdminSecuritySettingsController::class, 'index'])
            ->name('settings.security');
        Route::put('/settings/security', [AdminSecuritySettingsController::class, 'update'])
            ->name('settings.security.update');
        Route::post('/settings/security/logs/cleanup', [AdminSecuritySettingsController::class, 'cleanup'])
            ->middleware('throttle:3,1')
            ->name('settings.security.logs.cleanup');
        Route::get('/settings/system', [AdminSettingsController::class, 'system'])->name('settings.system');
        Route::put('/settings/system/monitoring', [AdminSettingsController::class, 'updateSystemMonitoring'])
            ->name('settings.system.monitoring.update');
        Route::get('/settings/languages', [AdminSettingsController::class, 'languages'])->name('settings.languages');
        Route::put('/settings/languages', [AdminSettingsController::class, 'updateLanguages'])->name('settings.languages.update');

        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
