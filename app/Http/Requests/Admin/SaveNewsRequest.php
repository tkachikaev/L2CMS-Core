<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_published' => $this->boolean('is_published'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:200000'],
            'published_at' => ['nullable', 'date'],
            'is_published' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'заголовок',
            'excerpt' => 'краткое описание',
            'body' => 'текст новости',
            'published_at' => 'дата публикации',
            'is_published' => 'статус публикации',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Поле «:attribute» обязательно для заполнения.',
            'max' => 'Поле «:attribute» превышает допустимую длину.',
            'date' => 'Поле «:attribute» содержит некорректную дату.',
            'boolean' => 'Поле «:attribute» содержит некорректное значение.',
        ];
    }
}
