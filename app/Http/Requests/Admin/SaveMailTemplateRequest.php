<?php

namespace App\Http\Requests\Admin;

use App\Services\MailTemplateSettings;
use Illuminate\Foundation\Http\FormRequest;

class SaveMailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $template = (string) $this->route('template');
        $requiresAction = app(MailTemplateSettings::class)->exists($template)
            && app(MailTemplateSettings::class)->requiresAction($template);

        return [
            'subject' => ['required', 'string', 'max:200'],
            'heading' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'action_text' => [$requiresAction ? 'required' : 'nullable', 'string', 'max:100'],
            'footer' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'subject.required' => 'Укажите тему письма.',
            'subject.max' => 'Тема письма не должна превышать 200 символов.',
            'heading.required' => 'Укажите заголовок письма.',
            'heading.max' => 'Заголовок письма не должен превышать 150 символов.',
            'body.required' => 'Укажите основной текст письма.',
            'body.max' => 'Основной текст письма не должен превышать 5000 символов.',
            'action_text.required' => 'Укажите текст кнопки.',
            'action_text.max' => 'Текст кнопки не должен превышать 100 символов.',
            'footer.max' => 'Дополнительный текст не должен превышать 3000 символов.',
        ];
    }
}
