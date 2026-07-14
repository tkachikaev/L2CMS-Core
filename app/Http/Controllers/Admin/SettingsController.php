<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveGameServerSettingsRequest;
use App\Http\Requests\Admin\SaveGeneralSettingsRequest;
use App\Http\Requests\Admin\SaveLanguageSettingsRequest;
use App\Http\Requests\Admin\SaveMailSettingsRequest;
use App\Http\Requests\Admin\SaveMailTemplateRequest;
use App\Http\Requests\Admin\SaveRegistrationSettingsRequest;
use App\Http\Requests\Admin\SendCustomMailRequest;
use App\Http\Requests\Admin\SendMailTemplateTestRequest;
use App\Http\Requests\Admin\SendTestMailRequest;
use App\Mail\CustomHtmlMail;
use App\Models\GameServer;
use App\Notifications\MailTemplateTestNotification;
use App\Services\AuditLogger;
use App\Services\GameServerSettings;
use App\Services\Localization\LanguageManager;
use App\Services\Mail\CustomMailHtmlSanitizer;
use App\Services\MailSettings;
use App\Services\MailTemplateSettings;
use App\Services\RegistrationSettings;
use App\Services\Settings\SettingsImageStorage;
use App\Services\SiteSettings;
use App\Services\SystemInformation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function general(SiteSettings $siteSettings): View
    {
        return view('admin.settings.general', [
            'settings' => $siteSettings->values(),
            'translations' => $siteSettings->translations(),
            'languages' => app(LanguageManager::class)->enabled(),
            'defaultLocale' => app(LanguageManager::class)->default(),
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

            $translations = is_array($validated['translations'] ?? null)
                ? $validated['translations']
                : [];

            $siteSettings->update([
                'name' => (string) ($validated['site_name'] ?? $current['name']),
                'description' => (string) ($validated['site_description'] ?? $current['description']),
                'logo' => $logo,
                'favicon' => $favicon,
                'timezone' => (string) $validated['timezone'],
                'admin_email' => (string) ($validated['admin_email'] ?? ''),
                'footer_text' => (string) ($validated['footer_text'] ?? $current['footer_text']),
            ], $translations);
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
            target: __('General settings'),
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
            ->with('status', __('General settings saved.'));
    }

    public function gameServer(GameServerSettings $gameServerSettings): View
    {
        return view('admin.settings.game-server', [
            'servers' => $gameServerSettings->all(),
            'languages' => app(LanguageManager::class)->enabled(),
            'defaultLocale' => app(LanguageManager::class)->default(),
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
            ->with('status', __('Game server added.'));
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
            ->with('status', __('Game server settings saved.'));
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
            ->with('status', __('Game server :name deleted.', ['name' => $name]));
    }

    public function loginServer(): View
    {
        return $this->placeholder(
            title: __('Login Server'),
            description: __('Login Server connection and status settings will appear here.'),
        );
    }

    public function system(SystemInformation $systemInformation): View
    {
        return view('admin.settings.system', [
            'system' => $systemInformation->collect(),
        ]);
    }

    public function languages(LanguageManager $languages): View
    {
        return view('admin.settings.languages', [
            'installedLanguages' => $languages->installed(),
            'enabledLocales' => $languages->enabledCodes(),
            'defaultLocale' => $languages->default(),
            'fallbackLocale' => $languages->fallback(),
        ]);
    }

    public function updateLanguages(
        SaveLanguageSettingsRequest $request,
        LanguageManager $languages,
    ): RedirectResponse {
        $validated = $request->validated();
        $before = [
            'enabled' => $languages->enabledCodes(),
            'default' => $languages->default(),
            'fallback' => $languages->fallback(),
        ];

        $languages->update(
            enabled: array_values(array_map('strval', (array) $validated['enabled_locales'])),
            default: (string) $validated['default_locale'],
            fallback: (string) $validated['fallback_locale'],
        );

        $after = [
            'enabled' => $languages->enabledCodes(),
            'default' => $languages->default(),
            'fallback' => $languages->fallback(),
        ];

        $this->auditLogger->success(
            category: 'admin',
            action: 'settings.languages_updated',
            target: __('Language settings'),
            details: ['changes' => $this->auditChanges($before, $after)],
        );

        return redirect()
            ->route('admin.settings.languages')
            ->with('status', __('Language settings saved.'));
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
            target: __('Registration settings'),
            details: ['changes' => $this->auditChanges($before, $after)],
        );

        return redirect()
            ->route('admin.settings.registration')
            ->with('status', __('Registration settings saved.'));
    }

    public function mail(MailSettings $mailSettings, MailTemplateSettings $mailTemplates): View
    {
        return view('admin.settings.mail', [
            'settings' => $mailSettings->values(),
            'mailTemplates' => $mailTemplates->navigation(app()->getLocale()),
        ]);
    }

    public function customMail(MailSettings $mailSettings, MailTemplateSettings $mailTemplates): View
    {
        return view('admin.settings.mail-custom', [
            'mailSettings' => $mailSettings->values(),
            'mailTemplates' => $mailTemplates->navigation(app()->getLocale()),
            'templateLocale' => app()->getLocale(),
            'exampleHtml' => $this->customMailExample(),
        ]);
    }

    public function sendCustomMail(
        SendCustomMailRequest $request,
        MailSettings $mailSettings,
        CustomMailHtmlSanitizer $sanitizer,
    ): RedirectResponse {
        if (! $mailSettings->isReady()) {
            return back()->withInput()->withErrors([
                'recipient' => __('Configure SMTP and successfully send a test email from the Connection tab first.'),
            ]);
        }

        $validated = $request->validated();
        $address = trim((string) $validated['recipient']);
        $subject = trim((string) preg_replace('/[\r\n\t]+/u', ' ', strip_tags((string) $validated['subject'])));
        $safeHtml = $sanitizer->sanitize((string) $validated['html']);

        if ($sanitizer->plainText($safeHtml) === '') {
            return back()->withInput()->withErrors([
                'html' => __('The email must contain visible text.'),
            ]);
        }

        $mailSettings->applyConfiguration();

        try {
            Mail::to($address)->send(new CustomHtmlMail($subject, $safeHtml));
        } catch (Throwable $exception) {
            Log::warning('Custom email sending failed.', [
                'exception' => $exception::class,
            ]);
            $this->auditLogger->failed(
                category: 'mail',
                action: 'mail.custom_failed',
                target: $address,
                details: [
                    'subject' => $subject,
                    'html_length' => strlen($safeHtml),
                    'exception_class' => $exception::class,
                ],
            );

            return back()->withInput()->withErrors([
                'recipient' => __('The custom email could not be sent. Check SMTP and try again.'),
            ]);
        }

        $this->auditLogger->success(
            category: 'mail',
            action: 'mail.custom_sent',
            target: $address,
            details: [
                'subject' => $subject,
                'html_length' => strlen($safeHtml),
            ],
        );

        return redirect()
            ->route('admin.settings.mail.custom')
            ->with('status', __('Custom email sent to :email.', ['email' => $address]));
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
            target: __('Mail settings'),
            details: [
                'changes' => $this->auditChanges($before, $after),
                'smtp_password_changed' => $passwordChanged,
            ],
        );

        return redirect()
            ->route('admin.settings.mail')
            ->with('status', __('Mail settings saved. Send a test email to verify them.'));
    }

    public function testMail(
        SendTestMailRequest $request,
        MailSettings $mailSettings,
    ): RedirectResponse {
        if (! $mailSettings->isConfigured()) {
            return back()->withErrors([
                'test_email' => __('Save the complete mail settings first.'),
            ]);
        }

        $mailSettings->applyConfiguration();
        $address = (string) $request->validated()['test_email'];

        try {
            Mail::raw(
                __('This is a test email from L2Forge CMS.')."\n\n".__('If you received it, the SMTP settings are working correctly.'),
                function (Message $message) use ($address): void {
                    $message->to($address)->subject(__('Mail test — :site', ['site' => site_name()]));
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
                'test_email' => __('The test email could not be sent. Check the server, port, encryption, username and password.'),
            ]);
        }

        $this->auditLogger->success(
            category: 'mail',
            action: 'mail.test_sent',
            target: $address,
        );

        return redirect()
            ->route('admin.settings.mail')
            ->with('status', __('Test email sent successfully to :email.', ['email' => $address]));
    }

    public function mailTemplate(
        Request $request,
        string $template,
        MailTemplateSettings $mailTemplates,
        MailSettings $mailSettings,
        LanguageManager $languages,
    ): View {
        abort_unless($mailTemplates->exists($template), 404);
        $locale = $languages->normalizeCode((string) $request->query('locale', app()->getLocale()));
        if ($locale === null || ! $languages->isEnabled($locale)) {
            $locale = $languages->default();
        }

        $values = $mailTemplates->values($template, $locale);

        return view('admin.settings.mail-template', [
            'template' => $values,
            'mailTemplates' => $mailTemplates->navigation($locale),
            'preview' => $mailTemplates->render($template, $mailTemplates->demoVariables($template, $locale), $locale),
            'mailSettings' => $mailSettings->values(),
            'languages' => $languages->enabled(),
            'templateLocale' => $locale,
        ]);
    }

    public function updateMailTemplate(
        SaveMailTemplateRequest $request,
        string $template,
        MailTemplateSettings $mailTemplates,
    ): RedirectResponse {
        abort_unless($mailTemplates->exists($template), 404);
        $validated = $request->validated();
        $locale = (string) $validated['locale'];
        $values = [
            'subject' => trim((string) $validated['subject']),
            'header' => trim((string) $validated['header']),
            'heading' => trim((string) $validated['heading']),
            'body' => trim((string) $validated['body']),
            'action_text' => trim((string) ($validated['action_text'] ?? '')),
            'footer' => trim((string) ($validated['footer'] ?? '')),
        ];

        $errors = [];
        foreach ($mailTemplates->unknownVariables($template, $values, $locale) as $field => $variables) {
            $errors[$field] = __('Unknown variables: ').implode(', ', array_map(
                static fn (string $variable): string => '{{'.$variable.'}}',
                $variables,
            )).'.';
        }

        foreach ($mailTemplates->fieldsContainingHtml($values) as $field) {
            $errors[$field] = __('HTML tags are not supported. Use plain text and the available variables.');
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $before = $mailTemplates->values($template, $locale);
        $mailTemplates->update($template, $values, $locale);
        $after = $mailTemplates->values($template, $locale);
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
                'locale' => $locale,
                'changed_fields' => $changedFields,
            ],
        );

        return redirect()
            ->route('admin.settings.mail.template', ['template' => $template, 'locale' => $locale])
            ->with('status', __('Mail template saved.'));
    }

    public function resetMailTemplate(
        Request $request,
        string $template,
        MailTemplateSettings $mailTemplates,
        LanguageManager $languages,
    ): RedirectResponse {
        abort_unless($mailTemplates->exists($template), 404);
        $locale = $languages->normalizeCode((string) $request->input('locale'));
        abort_unless($locale !== null && $languages->isEnabled($locale), 422);
        $title = $mailTemplates->values($template, $locale)['title'];
        $mailTemplates->reset($template, $locale);

        $this->auditLogger->success(
            category: 'mail',
            action: 'mail.template_reset',
            target: $title,
            details: ['template' => $template, 'locale' => $locale],
        );

        return redirect()
            ->route('admin.settings.mail.template', ['template' => $template, 'locale' => $locale])
            ->with('status', __('Default mail template restored.'));
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
                'test_email' => __('Configure SMTP and successfully send a test email from the Connection tab first.'),
            ]);
        }

        $validated = $request->validated();
        $address = (string) $validated['test_email'];
        $locale = (string) $validated['locale'];
        $title = $mailTemplates->values($template, $locale)['title'];
        $mailSettings->applyConfiguration();

        try {
            Notification::route('mail', $address)
                ->notify(new MailTemplateTestNotification($template, $locale));
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
                    'locale' => $locale,
                    'exception_class' => $exception::class,
                ],
            );

            return back()->withErrors([
                'test_email' => __('The test email could not be sent. Check SMTP and try again.'),
            ]);
        }

        $this->auditLogger->success(
            category: 'mail',
            action: 'mail.template_test_sent',
            target: $title,
            details: ['template' => $template, 'locale' => $locale],
        );

        return redirect()
            ->route('admin.settings.mail.template', ['template' => $template, 'locale' => $locale])
            ->with('status', __('Test template sent to :email.', ['email' => $address]));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
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
            'header' => (string) ($values['header'] ?? ''),
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
     * @param  array<string, mixed>  $validated
     * @return array{name: string, rates: string|null, chronicle: string|null, mode: string|null}
     */
    private function gameServerValues(array $validated): array
    {
        $translations = [];
        foreach ((array) ($validated['translations'] ?? []) as $locale => $translation) {
            if (is_array($translation)) {
                $translations[(string) $locale] = (string) ($translation['name'] ?? '');
            }
        }

        return [
            'name' => (string) ($validated['server_name'] ?? ''),
            'translations' => $translations,
            'rates' => isset($validated['server_rates']) ? (string) $validated['server_rates'] : null,
            'chronicle' => isset($validated['server_chronicle']) ? (string) $validated['server_chronicle'] : null,
            'mode' => isset($validated['server_mode']) ? (string) $validated['server_mode'] : null,
        ];
    }

    private function customMailExample(): string
    {
        $siteName = e(site_name());
        $locale = e(app()->getLocale());
        $heading = e(__('Custom email heading'));
        $body = e(__('Replace this text with your message.'));
        $button = e(__('Open website'));
        $url = e(rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/'));

        return <<<HTML
<!doctype html>
<html lang="{$locale}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$heading}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="padding:22px 28px;background:#111827;color:#ffffff;font-size:20px;font-weight:700;">{$siteName}</td>
                    </tr>
                    <tr>
                        <td style="padding:34px 28px;">
                            <h1 style="margin:0 0 18px;font-size:26px;line-height:1.25;">{$heading}</h1>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.65;">{$body}</p>
                            <a href="{$url}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:7px;font-weight:700;">{$button}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function placeholder(string $title, string $description): View
    {
        return view('admin.settings.placeholder', compact('title', 'description'));
    }
}
