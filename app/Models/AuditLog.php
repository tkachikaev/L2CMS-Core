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

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'admin' => 'Администраторы',
            'user' => 'Пользователи',
            'mail' => 'Почта',
            'system' => 'Система',
            default => str($this->category)->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'auth.login' => 'Вход в аккаунт',
            'auth.login_failed' => 'Неудачная попытка входа',
            'auth.logout' => 'Выход из аккаунта',
            'administrator.created' => 'Создан администратор',
            'administrator.updated' => 'Изменены данные администратора',
            'administrator.password_changed' => 'Изменён пароль администратора',
            'administrator.activated' => 'Включён администратор',
            'administrator.deactivated' => 'Отключён администратор',
            'news.created' => 'Создана новость',
            'news.updated' => 'Изменена новость',
            'news.deleted' => 'Удалена новость',
            'settings.general_updated' => 'Изменены основные настройки',
            'settings.registration_updated' => 'Изменены настройки регистрации',
            'settings.mail_updated' => 'Изменены почтовые настройки',
            'mail.template_updated' => 'Изменён шаблон письма',
            'mail.template_reset' => 'Восстановлен шаблон письма',
            'mail.template_test_sent' => 'Отправлен тестовый шаблон письма',
            'mail.template_test_failed' => 'Ошибка тестового шаблона письма',
            'game_server.created' => 'Добавлен игровой сервер',
            'game_server.updated' => 'Изменён игровой сервер',
            'game_server.deleted' => 'Удалён игровой сервер',
            'theme.activated' => 'Активирована тема',
            'user.registered' => 'Зарегистрирован пользователь',
            'user.email_verified' => 'Подтверждён email',
            'user.password_changed' => 'Изменён пароль',
            'user.password_reset_requested' => 'Запрошено восстановление пароля',
            'user.enabled' => 'Включён пользователь',
            'user.disabled' => 'Отключён пользователь',
            'user.verification_resent' => 'Повторно отправлено подтверждение email',
            'user.password_reset_sent' => 'Администратор отправил восстановление пароля',
            'mail.test_sent' => 'Отправлено тестовое письмо',
            'mail.test_failed' => 'Ошибка тестового письма',
            'mail.verification_sent' => 'Отправлено подтверждение email',
            'mail.verification_failed' => 'Ошибка подтверждения email',
            'mail.password_reset_sent' => 'Отправлено восстановление пароля',
            'mail.password_reset_failed' => 'Ошибка восстановления пароля',
            'mail.password_changed_sent' => 'Отправлено уведомление о смене пароля',
            'mail.password_changed_failed' => 'Ошибка уведомления о смене пароля',
            'audit.cleaned' => 'Очищен журнал действий',
            default => str($this->action)->replace(['.', '_', '-'], ' ')->headline()->toString(),
        };
    }

    public function resultLabel(): string
    {
        return $this->result === 'failed' ? 'Ошибка' : 'Успешно';
    }

    public function actorTypeLabel(): string
    {
        return match ($this->actor_type) {
            'admin' => 'Администратор',
            'user' => 'Пользователь',
            'system' => 'Система',
            null, '' => 'Без авторизации',
            default => str($this->actor_type)->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    public function actorLabel(): string
    {
        if ($this->actor_name !== null && $this->actor_name !== '') {
            return $this->actor_name;
        }

        return $this->actor_type === 'system' ? 'Система' : 'Не определён';
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
