<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:1024'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:100'],
            'notification_email' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'smtp_host.required' => 'Укажите SMTP-сервер.',
            'smtp_port.required' => 'Укажите порт SMTP-сервера.',
            'smtp_port.between' => 'Порт должен быть в диапазоне от 1 до 65535.',
            'from_address.required' => 'Укажите email отправителя.',
            'from_address.email' => 'Email отправителя указан неверно.',
            'from_name.required' => 'Укажите имя отправителя.',
            'notification_email.email' => 'Email для системных уведомлений указан неверно.',
        ];
    }
}
