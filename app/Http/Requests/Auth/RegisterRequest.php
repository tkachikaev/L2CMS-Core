<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::lower(trim((string) $this->input('name'))),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:32', 'alpha_dash:ascii', 'unique:users,name'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите логин.',
            'name.min' => 'Логин должен содержать не менее 3 символов.',
            'name.max' => 'Логин не должен быть длиннее 32 символов.',
            'name.alpha_dash' => 'Логин может содержать латинские буквы, цифры, дефис и подчёркивание.',
            'name.unique' => 'Этот логин уже занят.',
            'email.required' => 'Укажите email.',
            'email.email' => 'Email указан неверно.',
            'email.unique' => 'Этот email уже используется.',
            'password.required' => 'Укажите пароль.',
            'password.confirmed' => 'Пароли не совпадают.',
        ];
    }
}
