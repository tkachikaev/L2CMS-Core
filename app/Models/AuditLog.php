<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'category',
        'action',
        'actor_type',
        'actor_id',
        'actor_name',
        'target_type',
        'target_id',
        'target_name',
        'result',
        'ip_address',
        'user_agent',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function categoryLabelFor(string $category): string
    {
        return match ($category) {
            'admin' => __('Administrators'),
            'user' => __('Users'),
            'mail' => __('Mail'),
            'game_account' => __('Game accounts'),
            'system' => __('System'),
            default => str($category)->replace(['_', '-', '.'], ' ')->headline()->toString(),
        };
    }

    public function categoryLabel(): string
    {
        return self::categoryLabelFor((string) $this->category);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'auth.login' => __('Account signed in'),
            'auth.login_failed' => __('Failed sign-in attempt'),
            'auth.logout' => __('Account signed out'),
            'administrator.created' => __('Administrator created'),
            'administrator.updated' => __('Administrator details changed'),
            'administrator.password_changed' => __('Administrator password changed'),
            'administrator.activated' => __('Administrator enabled'),
            'administrator.deactivated' => __('Administrator disabled'),
            'news.created' => __('News created'),
            'news.updated' => __('News changed'),
            'news.deleted' => __('News deleted'),
            'settings.general_updated' => __('General settings changed'),
            'settings.registration_updated' => __('Registration settings changed'),
            'settings.game_accounts_updated' => __('Game account settings changed'),
            'settings.mail_updated' => __('Mail settings changed'),
            'settings.languages_updated' => __('Language settings changed'),
            'settings.security_updated' => __('Security settings changed'),
            'mail.template_updated' => __('Mail template changed'),
            'mail.template_reset' => __('Mail template restored'),
            'mail.template_test_sent' => __('Mail template test sent'),
            'mail.template_test_failed' => __('Mail template test failed'),
            'mail.custom_sent' => __('Custom email sent'),
            'mail.custom_failed' => __('Custom email failed'),
            'game_server.created' => __('Game server added'),
            'game_server.updated' => __('Game server changed'),
            'game_server.deleted' => __('Game server deleted'),
            'game_server.connection_updated' => __('GameServer database connection changed'),
            'game_server.connection_tested' => __('GameServer database connection tested'),
            'login_server.created' => __('LoginServer added'),
            'login_server.updated' => __('LoginServer changed'),
            'login_server.deleted' => __('LoginServer deleted'),
            'login_server.connection_tested' => __('LoginServer database connection tested'),
            'theme.activated' => __('Theme activated'),
            'user.registered' => __('User registered'),
            'user.email_verified' => __('Verified email'),
            'user.password_changed' => __('Password changed'),
            'user.password_reset_requested' => __('Password reset requested'),
            'user.enabled' => __('User enabled'),
            'user.disabled' => __('User disabled'),
            'user.verification_resent' => __('Email verification resent'),
            'user.password_reset_sent' => __('Administrator sent password reset'),
            'user.game_account_created' => __('Game account created'),
            'user.game_account_creation_failed' => __('Game account creation failed'),
            'user.game_account_password_changed' => __('Game account password changed'),
            'user.game_account_password_change_failed' => __('Game account password change failed'),
            'mail.test_sent' => __('Test email sent'),
            'mail.test_failed' => __('Test email failed'),
            'mail.verification_sent' => __('Email verification sent'),
            'mail.verification_failed' => __('Email verification failed'),
            'mail.password_reset_sent' => __('Password reset sent'),
            'mail.password_reset_failed' => __('Password reset failed'),
            'mail.password_changed_sent' => __('Password change notification sent'),
            'mail.password_changed_failed' => __('Password change notification failed'),
            'audit.cleaned' => __('Audit log cleaned'),
            'security.logs_cleaned' => __('Expired security logs cleaned'),
            default => str((string) $this->action)->replace(['.', '_', '-'], ' ')->headline()->toString(),
        };
    }

    public function resultLabel(): string
    {
        return $this->result === 'failed' ? __('Failed') : __('Successful');
    }

    public function actorTypeLabel(): string
    {
        return match ($this->actor_type) {
            'admin' => __('Administrator'),
            'user' => __('User'),
            'system' => __('System'),
            null, '' => __('Unauthenticated'),
            default => str((string) $this->actor_type)->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    public function actorLabel(): string
    {
        if ($this->actor_name !== null && $this->actor_name !== '') {
            return $this->actor_name;
        }

        return $this->actor_type === 'system' ? __('System') : __('Unknown');
    }

    public function targetLabel(): string
    {
        if ($this->target_name !== null && $this->target_name !== '') {
            return $this->target_name;
        }

        if ($this->target_type !== null && $this->target_type !== '') {
            return $this->target_id !== null
                ? $this->target_type.' #'.$this->target_id
                : $this->target_type;
        }

        return '—';
    }
}
