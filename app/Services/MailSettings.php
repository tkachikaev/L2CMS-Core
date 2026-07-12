<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class MailSettings
{
    public const KEY_HOST = 'mail.smtp_host';
    public const KEY_PORT = 'mail.smtp_port';
    public const KEY_ENCRYPTION = 'mail.encryption';
    public const KEY_USERNAME = 'mail.username';
    public const KEY_PASSWORD = 'mail.password_encrypted';
    public const KEY_FROM_ADDRESS = 'mail.from_address';
    public const KEY_FROM_NAME = 'mail.from_name';
    public const KEY_ADMIN_EMAIL = 'mail.admin_email';
    public const KEY_TESTED_AT = 'mail.tested_at';

    public function __construct(private readonly CmsSettings $settings)
    {
    }

    /**
     * @return array{
     *     host: string,
     *     port: int,
     *     encryption: string,
     *     username: string,
     *     password_saved: bool,
     *     password_valid: bool,
     *     from_address: string,
     *     from_name: string,
     *     admin_email: string,
     *     tested_at: string|null,
     *     configured: bool,
     *     ready: bool
     * }
     */
    public function values(): array
    {
        $defaults = [
            self::KEY_HOST => '',
            self::KEY_PORT => '587',
            self::KEY_ENCRYPTION => 'tls',
            self::KEY_USERNAME => '',
            self::KEY_PASSWORD => '',
            self::KEY_FROM_ADDRESS => '',
            self::KEY_FROM_NAME => (string) config('app.name', 'L2Forge CMS'),
            self::KEY_ADMIN_EMAIL => '',
            self::KEY_TESTED_AT => null,
        ];

        $values = $this->settings->getMany($defaults);
        $host = trim((string) ($values[self::KEY_HOST] ?? ''));
        $port = max(1, min(65535, (int) ($values[self::KEY_PORT] ?? 587)));
        $encryption = $this->normalizeEncryption((string) ($values[self::KEY_ENCRYPTION] ?? 'tls'));
        $username = trim((string) ($values[self::KEY_USERNAME] ?? ''));
        $fromAddress = trim((string) ($values[self::KEY_FROM_ADDRESS] ?? ''));
        $fromName = trim((string) ($values[self::KEY_FROM_NAME] ?? ''));
        $adminEmail = trim((string) ($values[self::KEY_ADMIN_EMAIL] ?? ''));
        $encryptedPassword = (string) ($values[self::KEY_PASSWORD] ?? '');
        $testedAt = trim((string) ($values[self::KEY_TESTED_AT] ?? ''));
        $passwordSaved = $encryptedPassword !== '';
        $passwordValid = ! $passwordSaved || $this->canDecryptPassword($encryptedPassword);
        $configured = $host !== ''
            && $port > 0
            && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false
            && $passwordValid;

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password_saved' => $passwordSaved,
            'password_valid' => $passwordValid,
            'from_address' => $fromAddress,
            'from_name' => $fromName !== '' ? $fromName : (string) config('app.name', 'L2Forge CMS'),
            'admin_email' => $adminEmail,
            'tested_at' => $testedAt !== '' ? $testedAt : null,
            'configured' => $configured,
            'ready' => $configured && $testedAt !== '',
        ];
    }

    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     encryption: string,
     *     username: string,
     *     password: string|null,
     *     from_address: string,
     *     from_name: string,
     *     admin_email: string
     * } $values
     */
    public function update(array $values): void
    {
        $existingPassword = $this->settings->get(self::KEY_PASSWORD, '') ?? '';
        $password = $values['password'];
        $encryptedPassword = is_string($password) && $password !== ''
            ? Crypt::encryptString($password)
            : $existingPassword;

        $this->settings->setMany([
            self::KEY_HOST => trim($values['host']),
            self::KEY_PORT => (string) $values['port'],
            self::KEY_ENCRYPTION => $this->normalizeEncryption($values['encryption']),
            self::KEY_USERNAME => trim($values['username']),
            self::KEY_PASSWORD => $encryptedPassword,
            self::KEY_FROM_ADDRESS => trim($values['from_address']),
            self::KEY_FROM_NAME => trim($values['from_name']),
            self::KEY_ADMIN_EMAIL => trim($values['admin_email']),
            self::KEY_TESTED_AT => null,
        ]);
    }

    public function markTested(): void
    {
        $this->settings->set(self::KEY_TESTED_AT, now()->toIso8601String());
    }

    public function isConfigured(): bool
    {
        return $this->values()['configured'];
    }

    public function isReady(): bool
    {
        return $this->values()['ready'];
    }

    public function applyConfiguration(): void
    {
        $values = $this->values();

        if (! $values['configured']) {
            return;
        }
        $password = $this->decryptedPassword();
        $scheme = $values['encryption'] === 'ssl' ? 'smtps' : 'smtp';

        config()->set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'scheme' => $scheme,
                'url' => null,
                'host' => $values['host'],
                'port' => $values['port'],
                'username' => $values['username'] !== '' ? $values['username'] : null,
                'password' => $password !== '' ? $password : null,
                'timeout' => 15,
                'local_domain' => parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST),
                'auto_tls' => $values['encryption'] !== 'none',
            ],
            'mail.from.address' => $values['from_address'],
            'mail.from.name' => $values['from_name'],
        ]);

        try {
            Mail::purge('smtp');
        } catch (Throwable) {
            // The mail manager may not be resolved yet during application boot.
        }
    }

    private function canDecryptPassword(string $encrypted): bool
    {
        if ($encrypted === '') {
            return true;
        }

        try {
            Crypt::decryptString($encrypted);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    private function decryptedPassword(): string
    {
        $encrypted = (string) ($this->settings->get(self::KEY_PASSWORD, '') ?? '');

        if ($encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return '';
        }
    }

    private function normalizeEncryption(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['tls', 'ssl', 'none'], true) ? $value : 'tls';
    }
}
