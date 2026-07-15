<?php

namespace App\Http\Requests\Account;

use App\Services\GameAccounts\GameAccountCredentialPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangeGameAccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'game_password' => ['required', 'string', 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (app(GameAccountCredentialPolicy::class)->passwordErrors((string) $this->input('game_password')) as $error) {
                $validator->errors()->add('game_password', $error);
            }
        });
    }
}
