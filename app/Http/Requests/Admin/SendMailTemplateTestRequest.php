<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendMailTemplateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'test_email' => ['required', 'email:rfc', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'test_email.required' => 'Укажите адрес для тестового письма.',
            'test_email.email' => 'Адрес для тестового письма указан неверно.',
        ];
    }
}
