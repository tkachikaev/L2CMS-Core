<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveGameServerSettingsRequest;
use App\Http\Requests\Admin\SaveGeneralSettingsRequest;
use App\Http\Requests\Admin\SaveMailSettingsRequest;
use App\Http\Requests\Admin\SaveMailTemplateRequest;
use App\Http\Requests\Admin\SaveRegistrationSettingsRequest;
use App\Http\Requests\Admin\SendMailTemplateTestRequest;
use App\Http\Requests\Admin\SendTestMailRequest;
use App\Models\GameServer;
use App\Notifications\MailTemplateTestNotification;
use App\Services\AuditLogger;
use App\Services\GameServerSettings;
use App\Services\MailSettings;
use App\Services\MailTemplateSettings;
use App\Services\RegistrationSettings;
use App\Services\Settings\SettingsImageStorage;
use App\Services\SiteSettings;
use App\Services\SystemInformation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function general(SiteSettings $siteSettings): View
    {
        return view('admin.settings.general', [
            'settings' => $siteSettings->values(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function updateGeneral(
        SaveGeneralSettingsRequest $request,
        SiteSettings $siteSettings,
        SettingsImageStorage $images,
    ): RedirectResponse {
        $validated = $request->validated();
        $current = $siteSettings->values();
        $storedLogo = null;
        $storedFavicon = null;

        try {
            if ($request->hasFile('logo')) {
                $storedLogo = $images->store($request->file('logo'), 'logo');
            }

            if ($request->hasFile('favicon')) {
                $storedFavicon = $images->store($request->file('favicon'), 'favicon');
            }

            $logo = $storedLogo
                ?? ($request->boolean('remove_logo') ? null : $current['logo']);
            $favicon = $storedFavicon
                ?? ($request->boolean('remove_favicon') ? null : $current['favicon']);

            $siteSettings->update([
                'name' => (string) $validated['site_name'],
                'description' => (string) ($validated['site_description'] ?? ''),
                'logo' => $logo,
                'favicon' => $favicon,
                'timezone' => (string) $validated['timezone'],
                'admin_email' => (string) ($validated['admin_email'] ?? ''),
                'footer_text' => (string) ($validated['footer_text'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            if ($storedLogo !== null) {
                $images->delete($storedLogo, 'logo');
            }

            if ($storedFavicon !== null) {
                $images->delete($storedFavicon, 'favicon');
            }

            throw $exception;
        }

        if ($current['logo'] !== null && $current['logo'] !== $logo) {
            $images->delete($current['logo'], 'logo');
        }

        if ($current['favicon'] !== null && $current['favicon'] !== $favicon) {
            $images->delete($current['favicon'], 'favicon');
        }

        $after = $siteSettings->values();
        $this->auditLogger->success(
            category: 'admin',
            action: 'settings.general_updated',
            target: 'Основные настройки',
            details: [
                'changes' => $this->auditChanges(
                    $this->generalAuditValues($current),
                    $this->generalAuditValues($after),
                ),
                'logo_changed' => $current['logo'] !== $after['logo'],
                'favicon_changed' => $current['favicon'] !== $after['favicon'],
            ],
        );

        return redirect()
            ->route('admin.settings.general')
            ->with('status', 'Основные настройки сохранены.');
    }

    public function gameServer(GameServerSettings $gameServerSettings): View
    {
        return view('admin.settings.game-server', [
            'servers' => $gameServerSettings->all(),
        ]);
    }

    public function storeGameServer(
        SaveGameServerSettingsRequest $request,
        GameServerSettings $gameServerSettings,
    ): RedirectResponse {
        $validated = $request->validated();

        $gameServer = $gameServerSettings->create($this->gameServerValues($validated));

        $this->auditLogger->success(
            category: 'admin',
            action: 'game_server.created',
            target: $gameServer,
            details: ['values' => $this->gameServerAuditValues($gameServer)],
        );

        return redirect()
            ->route('admin.settings.game-server')
            ->with('status', 'Игровой сервер добавлен.');
    }

    public function updateGameServer(
        SaveGameServerSettingsRequest $request,
        GameServer $gameServer,
        GameServerSettings $gameServerSettings,
    ): RedirectResponse {
        $validated = $request->validated();
        $before = $this->gameServerAuditValues($gameServer);

        $gameServerSettings->update($gameServer, $this->gameServerValues($validated));
        $gameServer->refresh();

        $this->auditLogger->success(
            category: 'admin',
            action: 'game_server.updated',
            target: $gameServer,
            details: ['changes' => $this->auditChanges($before, $this->gameServerAuditValues($gameServer))],
        );

        return redirect()
            ->route('admin.settings.game-server')
            ->with('status', 'Настройки игрового сервера сохранены.');
    }

    public function destroyGameServer(
        GameServer $gameServer,
        GameServerSettings $gameServerSettings,
    ): RedirectResponse {
        $name = $gameServer->name;
        $gameServerId = $gameServer->id;
        $values = $this->gameServerAuditValues($gameServer);
        $gameServerSettings->delete($gameServer);

        $this->auditLogger->success(
            category: 'admin',
            action: 'game_server.deleted',
            target: $name,
            details: [
                'game_server_id' => $gameServerId,
                'values' => $values,
            ],
        );

        return redirect()
            ->route('admin.settings.game-server')
            ->with('status', 'Игровой сервер «'.$name.'» удалён.');
    }

    public function loginServer(): View
    {
        return $this->placeholder(
            title: 'Логин сервер',
            description: 'Здесь появятся параметры подключения и состояния логин-сервера.',
        );
    }

    public function system(SystemInformation $systemInformation): View
    {
        return view('admin.settings.system', [
            'system' => $systemInformation->collect(),
        ]);
    }

    public function registration(RegistrationSettings $registrationSettings, MailSettings $mailSettings): View
    {
        return view('admin.settings.registration', [
            'settings' => $registrationSettings->values(),
            'mailReady' => $mailSettings->isReady(),
        ]);
    }

    public function updateRegistration(
        SaveRegistrationSettingsRequest $request,
        RegistrationSettings $registrationSettings,
    ): RedirectResponse {
        $before = $registrationSettings->values();
        $registrationSettings->update(
            enabled: $request->boolean('registration_enabled'),
            emailVerificationRequired: $request->boolean('email_verification_required'),
        );
        $after = $registrationSettings->values();

        $this->auditLogger->success(
            category: 'admin',
            action: 'settings.registration_updated',
            target: 'Настройки регистрации',
            details: ['changes' => $this->auditChanges($before, $after)],
        );

        return redirect()
            ->route('admin.settings.registration')
            ->with('status', 'Настройки регистрации сохранены.');
    }

    public function mail(MailSettings $mailSettings, MailTemplateSettings $mailTemplates): View
    {
        return view('admin.settings.mail', [
            'settings' => $mailSettings->values(),
            'mailTemplates' => $mailTemplates->navigation(),
        ]);
    }

    public function updateMail(
        SaveMailSettingsRequest $request,
        MailSettings $mailSettings,
    ): RedirectResponse {
        $validated = $request->validated();
        $before = $this->mailAuditValues($mailSettings->values());
        $passwordChanged = isset($validated['smtp_password']) && $validated['smtp_password'] !== '';

        $mailSettings->update([
            'host' => (string) $validated['smtp_host'],
            'port' => (int) $validated['smtp_port'],
            'encryption' => (string) $validated['encryption'],
            'username' => (string) ($validated['smtp_username'] ?? ''),
            'password' => isset($validated['smtp_password']) && $validated['smtp_password'] !== ''
                ? (string) $validated['smtp_password']
                : null,
            'from_address' => (string) $validated['from_address'],
            'from_name' => (string) $validated['from_name'],
            'admin_email' => (string) ($validated['notification_email'] ?? ''),
        ]);
        $mailSettings->applyConfiguration();
        $after = $this->mailAuditValues($mailSettings->values());

        $this->auditLogger->success(
            category: 'admin',
            action: 'settings.mail_updated',
            target: 'Почтовые настройки',
            details: [
                'changes' => $this->auditChanges($before, $after),
                'smtp_password_changed' => $passwordChanged,
            ],
        );

        return redirect()
            ->route('admin.settings.mail')
            ->with('status', 'Почтовые настройки сохранены. Отправьте тестовое письмо для проверки.');
    }

    public function testMail(
        SendTestMailRequest $request,
        MailSettings $mailSettings,
    ): RedirectResponse {
        if (! $mailSettings->isConfigured()) {
            return back()->withErrors([
                'test_email' => 'Сначала сохраните полные почтовые настройки.',
            ]);
        }

        $mailSettings->applyConfiguration();
        $address = (string) $request->validated()['test_email'];

        try {
            Mail::raw(
                "Это тестовое письмо от L2Forge CMS.\n\nЕсли вы получили его, SMTP-настройки работают корректно.",
                function (Message $message) use ($address): void {
                    $message->to($address)->subject('Проверка почты — '.site_name());
                }
            );

            $mailSettings->markTested();
        } catch (Throwable $exception) {
            Log::warning('SMTP test failed.', [
                'exception' => $exception::class,
            ]);
            $this->auditLogger->failed(
                category: 'mail',
                action: 'mail.test_failed',
                target: $address,
                details: ['exception_class' => $exception::class],
            );

            return back()->withErrors([
                'test_email' => 'Тестовое письмо отправить не удалось. Проверьте сервер, порт, шифрование, логин и пароль.',
            ]);
        }

        $this->auditLogger->success(
            category: 'mail',
            action: 'mail.test_sent',
            target: $address,
        );

        return redirect()
            ->route('admin.settings.mail')
            ->with('status', 'Тестовое письмо успешно отправлено на '.$address.'.');
    }

    public function mailTemplate(
        string $template,
        MailTemplateSettings $mailTemplates,
        MailSettings $mailSettings,
    ): View {
        abort_unless($mailTemplates->exists($template), 404);

        $values = $mailTemplates->values($template);

        return view('admin.settings.mail-template', [
            'template' => $values,
            'mailTemplates' => $mailTemplates->navigation(),
            'preview' => $mailTemplates->render($template, $mailTemplates->demoVariables($template)),
            'mailSettings' => $mailSettings->values(),
        ]);
    }

    public function updateMailTemplate(
        SaveMailTemplateRequest $request,
        string $template,
        MailTemplateSettings $mailTemplates,
    ): RedirectResponse {
        abort_unless($mailTemplates->exists($template), 404);
        $validated = $request->validated();
        $values = [
            'subject' => trim((string) $validated['subject']),
            'heading' => trim((string) $validated['heading']),
            'body' => trim((string) $validated['body']),
            'action_text' => trim((string) ($validated['action_text'] ?? '')),
            'footer' => trim((string) ($validated['footer'] ?? '')),
        ];

        $errors = [];
        foreach ($mailTemplates->unknownVariables($template, $values) as $field => $variables) {
            $errors[$field] = 'Неизвестные переменные: '.implode(', ', array_map(
                static fn (string $variable): string => '{{'.$variable.'}}',
                $variables,
            )).'.';
        }

        foreach ($mailTemplates->fieldsContainingHtml($values) as $field) {
            $errors[$field] = 'HTML-теги не поддерживаются. Используйте обычный текст и доступные переменные.';
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $before = $mailTemplates->values($template);
        $mailTemplates->update($template, $values);
        $after = $mailTemplates->values($template);
        $changedFields = array_keys($this->auditChanges(
            $this->mailTemplateAuditValues($before),
            $this->mailTemplateAuditValues($after),
        ));

        $this->auditLogger->success(
            category: 'mail',
            action: 'mail.template_updated',
            target: $after['title'],
            details: [
                'template' => $template,
                'changed_fields' => $changedFields,
            ],
        );

        return redirect()
            ->route('admin.settings.mail.template', ['template' => $template])
            ->with('status', 'Шаблон письма сохранён.');
    }

    public function resetMailTemplate(
        string $template,
        MailTemplateSettings $mailTemplates,
    ): RedirectResponse {
        abort_unless($mailTemplates->exists($template), 404);
        $title = $mailTemplates->values($template)['title'];
        $mailTemplates->reset($template);

        $this->auditLogger->success(
            category: 'mail',
            action: 'mail.template_reset',
            target: $title,
            details: ['template' => $template],
        );

        return redirect()
            ->route('admin.settings.mail.template', ['template' => $template])
            ->with('status', 'Стандартный шаблон восстановлен.');
    }

    public function testMailTemplate(
        SendMailTemplateTestRequest $request,
        string $template,
        MailTemplateSettings $mailTemplates,
        MailSettings $mailSettings,
    ): RedirectResponse {
        abort_unless($mailTemplates->exists($template), 404);

        if (! $mailSettings->isReady()) {
            return back()->withErrors([
                'test_email' => 'Сначала настройте SMTP и успешно отправьте проверочное письмо во вкладке «Подключение».',
            ]);
        }

        $address = (string) $request->validated()['test_email'];
        $title = $mailTemplates->values($template)['title'];
        $mailSettings->applyConfiguration();

        try {
            Notification::route('mail', $address)
                ->notify(new MailTemplateTestNotification($template));
        } catch (Throwable $exception) {
            Log::warning('Mail template test failed.', [
                'template' => $template,
                'exception' => $exception::class,
            ]);
            $this->auditLogger->failed(
                category: 'mail',
                action: 'mail.template_test_failed',
                target: $title,
                details: [
                    'template' => $template,
                    'exception_class' => $exception::class,
                ],
            );

            return back()->withErrors([
                'test_email' => 'Тестовое письмо отправить не удалось. Проверьте SMTP и повторите попытку.',
            ]);
        }

        $this->auditLogger->success(
            category: 'mail',
            action: 'mail.template_test_sent',
            target: $title,
            details: ['template' => $template],
        );

        return redirect()
            ->route('admin.settings.mail.template', ['template' => $template])
            ->with('status', 'Тестовый шаблон отправлен на '.$address.'.');
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function auditChanges(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        return $changes;
    }

    /** @param array<string, mixed> $values */
    private function generalAuditValues(array $values): array
    {
        return [
            'name' => $values['name'] ?? '',
            'description' => $values['description'] ?? '',
            'timezone' => $values['timezone'] ?? '',
            'admin_email' => $values['admin_email'] ?? '',
            'footer_text' => $values['footer_text'] ?? '',
            'logo_configured' => ! empty($values['logo']),
            'favicon_configured' => ! empty($values['favicon']),
        ];
    }

    private function gameServerAuditValues(GameServer $gameServer): array
    {
        return [
            'name' => $gameServer->name,
            'rates' => $gameServer->rates,
            'chronicle' => $gameServer->chronicle,
            'mode' => $gameServer->mode,
        ];
    }

    /** @param array<string, mixed> $values @return array<string, string> */
    private function mailTemplateAuditValues(array $values): array
    {
        return [
            'subject' => (string) ($values['subject'] ?? ''),
            'heading' => (string) ($values['heading'] ?? ''),
            'body' => (string) ($values['body'] ?? ''),
            'action_text' => (string) ($values['action_text'] ?? ''),
            'footer' => (string) ($values['footer'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $values */
    private function mailAuditValues(array $values): array
    {
        return [
            'host' => $values['host'] ?? '',
            'port' => $values['port'] ?? 0,
            'encryption' => $values['encryption'] ?? '',
            'username' => $values['username'] ?? '',
            'from_address' => $values['from_address'] ?? '',
            'from_name' => $values['from_name'] ?? '',
            'admin_email' => $values['admin_email'] ?? '',
            'password_saved' => (bool) ($values['password_saved'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{name: string, rates: string|null, chronicle: string|null, mode: string|null}
     */
    private function gameServerValues(array $validated): array
    {
        return [
            'name' => (string) $validated['server_name'],
            'rates' => isset($validated['server_rates']) ? (string) $validated['server_rates'] : null,
            'chronicle' => isset($validated['server_chronicle']) ? (string) $validated['server_chronicle'] : null,
            'mode' => isset($validated['server_mode']) ? (string) $validated['server_mode'] : null,
        ];
    }

    private function placeholder(string $title, string $description): View
    {
        return view('admin.settings.placeholder', compact('title', 'description'));
    }
}
