<?php

namespace App\Http\Requests\Admin;

use App\Services\MailSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveRegistrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'registration_enabled' => ['nullable', 'boolean'],
            'email_verification_required' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('registration_enabled') || ! $this->boolean('email_verification_required')) {
                return;
            }

            if (! app(MailSettings::class)->isReady()) {
                $validator->errors()->add(
                    'email_verification_required',
                    'Сначала сохраните почтовые настройки и успешно отправьте тестовое письмо.'
                );
            }
        });
    }
}
