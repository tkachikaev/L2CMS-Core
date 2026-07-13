<?php

namespace App\Services;

use Illuminate\Notifications\Messages\MailMessage;
use InvalidArgumentException;

final class MailTemplateSettings
{
    public const EMAIL_VERIFICATION = 'email_verification';
    public const PASSWORD_RESET = 'password_reset';
    public const PASSWORD_CHANGED = 'password_changed';

    /** @var array<int, string> */
    private const EDITABLE_FIELDS = [
        'subject',
        'heading',
        'body',
        'action_text',
        'footer',
    ];

    public function __construct(private readonly CmsSettings $settings)
    {
    }

    /** @return array<string, array<string, mixed>> */
    public function navigation(): array
    {
        $items = [];

        foreach ($this->definitions() as $key => $definition) {
            $items[$key] = [
                'title' => (string) ($definition['title'] ?? $key),
                'description' => (string) ($definition['description'] ?? ''),
            ];
        }

        return $items;
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    /** @return array<string, mixed> */
    public function definition(string $key): array
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$key])) {
            throw new InvalidArgumentException('Unknown mail template: '.$key);
        }

        return $definitions[$key];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     requires_action: bool,
     *     variables: array<int, string>,
     *     subject: string,
     *     heading: string,
     *     body: string,
     *     action_text: string,
     *     footer: string,
     *     customized: bool
     * }
     */
    public function values(string $key): array
    {
        $definition = $this->definition($key);
        $defaults = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $defaults[$this->settingKey($key, $field)] = (string) ($definition[$field] ?? '');
        }

        $stored = $this->settings->getMany($defaults);
        $values = [];
        $customized = false;

        foreach (self::EDITABLE_FIELDS as $field) {
            $settingKey = $this->settingKey($key, $field);
            $default = (string) ($definition[$field] ?? '');
            $value = $stored[$settingKey] ?? $default;
            $values[$field] = is_string($value) ? $value : $default;

            if ($this->settings->get($settingKey) !== null) {
                $customized = true;
            }
        }

        return [
            'key' => $key,
            'title' => (string) ($definition['title'] ?? $key),
            'description' => (string) ($definition['description'] ?? ''),
            'requires_action' => (bool) ($definition['requires_action'] ?? false),
            'variables' => array_values(array_map('strval', (array) ($definition['variables'] ?? []))),
            'subject' => $values['subject'],
            'heading' => $values['heading'],
            'body' => $values['body'],
            'action_text' => $values['action_text'],
            'footer' => $values['footer'],
            'customized' => $customized,
        ];
    }

    /** @param array{subject: string, heading: string, body: string, action_text: string, footer: string} $values */
    public function update(string $key, array $values): void
    {
        $this->definition($key);
        $settings = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $settings[$this->settingKey($key, $field)] = (string) ($values[$field] ?? '');
        }

        $this->settings->setMany($settings);
    }

    public function reset(string $key): void
    {
        $this->definition($key);
        $values = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $values[$this->settingKey($key, $field)] = null;
        }

        $this->settings->setMany($values);
    }

    /**
     * @param array<string, string> $values
     * @return array<string, array<int, string>>
     */
    public function unknownVariables(string $key, array $values): array
    {
        $allowed = array_fill_keys($this->values($key)['variables'], true);
        $unknown = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $content = (string) ($values[$field] ?? '');
            preg_match_all('/\{\{([^{}]+)\}\}/u', $content, $matches);

            foreach ($matches[1] ?? [] as $match) {
                $variable = trim((string) $match);

                if ($variable !== '' && ! isset($allowed[$variable])) {
                    $unknown[$field][] = $variable;
                }
            }
        }

        foreach ($unknown as $field => $items) {
            $unknown[$field] = array_values(array_unique($items));
        }

        return $unknown;
    }

    /** @param array<string, string> $values @return array<int, string> */
    public function fieldsContainingHtml(array $values): array
    {
        $fields = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (preg_match('/<\s*\/?\s*[a-z][^>]*>/iu', (string) ($values[$field] ?? '')) === 1) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param array<string, string> $variables
     * @return array{subject: string, heading: string, body: string, action_text: string, footer: string}
     */
    public function render(string $key, array $variables): array
    {
        $values = $this->values($key);
        $rendered = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            $rendered[$field] = $this->replaceVariables((string) $values[$field], $variables);
        }

        return $rendered;
    }

    /**
     * @param array<string, string> $variables
     */
    public function mailMessage(string $key, array $variables, ?string $actionUrl = null): MailMessage
    {
        $template = $this->values($key);
        $rendered = $this->render($key, $variables);
        $message = (new MailMessage)
            ->subject($this->plainText($rendered['subject']))
            ->greeting($this->plainText($rendered['heading']));

        foreach ($this->blocks($rendered['body']) as $block) {
            $message->line(e($block));
        }

        if ($template['requires_action'] && $actionUrl !== null && trim($rendered['action_text']) !== '') {
            $message->action($this->plainText($rendered['action_text']), $actionUrl);
        }

        foreach ($this->blocks($rendered['footer']) as $block) {
            $message->line(e($block));
        }

        $siteName = $this->plainText($variables['site_name'] ?? site_name());

        return $message->salutation('С уважением, команда '.$siteName);
    }

    /** @return array<string, string> */
    public function userVariables(object $user, array $additional = []): array
    {
        $mail = app(MailSettings::class)->values();
        $site = app(SiteSettings::class)->values();
        $supportEmail = trim((string) ($mail['admin_email'] ?: ($site['admin_email'] ?? '')));

        if ($supportEmail === '') {
            $supportEmail = trim((string) ($mail['from_address'] ?? ''));
        }

        if ($supportEmail === '') {
            $supportEmail = 'администрацию сайта';
        }

        return array_merge([
            'site_name' => site_name(),
            'site_url' => rtrim((string) config('app.url', 'http://localhost'), '/'),
            'username' => (string) ($user->name ?? 'пользователь'),
            'user_email' => (string) ($user->email ?? ''),
            'support_email' => $supportEmail,
        ], $additional);
    }

    /** @return array<string, string> */
    public function demoVariables(string $key): array
    {
        $this->definition($key);
        $siteUrl = rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');

        return [
            'site_name' => site_name(),
            'site_url' => $siteUrl,
            'username' => 'TestPlayer',
            'user_email' => 'player@example.com',
            'verification_url' => $siteUrl.'/email/verify/example',
            'reset_url' => $siteUrl.'/reset-password/example',
            'expires_in' => '60 минут',
            'support_email' => 'support@example.com',
        ];
    }

    public function requiresAction(string $key): bool
    {
        return (bool) ($this->definition($key)['requires_action'] ?? false);
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        $templates = config('mail_templates.templates', []);

        return is_array($templates) ? $templates : [];
    }

    private function settingKey(string $template, string $field): string
    {
        return 'mail.template.'.$template.'.'.$field;
    }

    /** @param array<string, string> $variables */
    private function replaceVariables(string $value, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_][a-z0-9_]*)\s*\}\}/iu',
            static function (array $matches) use ($variables): string {
                $name = strtolower((string) ($matches[1] ?? ''));

                return array_key_exists($name, $variables)
                    ? (string) $variables[$name]
                    : (string) ($matches[0] ?? '');
            },
            $value,
        );
    }

    /** @return array<int, string> */
    private function blocks(string $value): array
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));

        if ($value === '') {
            return [];
        }

        $blocks = preg_split('/\n{2,}/u', $value) ?: [];

        return array_values(array_filter(array_map('trim', $blocks), static fn (string $block): bool => $block !== ''));
    }

    private function plainText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
