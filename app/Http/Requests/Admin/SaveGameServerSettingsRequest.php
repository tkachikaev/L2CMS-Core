<?php

namespace App\Http\Requests\Admin;

use App\Services\Localization\LanguageManager;
use Illuminate\Foundation\Http\FormRequest;

class SaveGameServerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $languages = app(LanguageManager::class);
        $rules = [
            'server_name' => ['nullable', 'required_without:translations', 'string', 'max:100'],
            'translations' => ['nullable', 'array'],
            'server_rates' => ['nullable', 'string', 'max:100'],
            'server_chronicle' => ['nullable', 'string', 'max:100'],
            'server_mode' => ['nullable', 'string', 'max:100'],
        ];

        foreach ($languages->enabledCodes() as $locale) {
            $nameRules = ['nullable', 'string', 'max:100'];
            if ($locale === $languages->default()) {
                array_splice($nameRules, 1, 0, ['required_with:translations']);
            }
            $rules['translations.'.$locale.'.name'] = $nameRules;
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'server_name' => __('server name'),
            'server_rates' => __('server rates'),
            'server_chronicle' => __('chronicle'),
            'server_mode' => __('server mode'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $translations = $this->input('translations');
        if (is_array($translations)) {
            foreach ($translations as $locale => $values) {
                if (is_array($values)) {
                    $translations[$locale]['name'] = trim((string) ($values['name'] ?? ''));
                }
            }
        }

        $this->merge([
            'server_name' => trim((string) $this->input('server_name')),
            'translations' => $translations,
            'server_rates' => trim((string) $this->input('server_rates')),
            'server_chronicle' => trim((string) $this->input('server_chronicle')),
            'server_mode' => trim((string) $this->input('server_mode')),
        ]);
    }
}
