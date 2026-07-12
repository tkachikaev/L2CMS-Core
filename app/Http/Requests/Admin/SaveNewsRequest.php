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
            'remove_cover_image' => $this->boolean('remove_cover_image'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:200000'],
            'cover_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=6000,max_height=6000',
            ],
            'remove_cover_image' => ['required', 'boolean'],
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
            'cover_image' => 'картинка-превью',
            'remove_cover_image' => 'удаление картинки-превью',
            'published_at' => 'дата публикации',
            'is_published' => 'статус публикации',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Поле «:attribute» обязательно для заполнения.',
            'max' => 'Поле «:attribute» превышает допустимый размер или длину.',
            'date' => 'Поле «:attribute» содержит некорректную дату.',
            'boolean' => 'Поле «:attribute» содержит некорректное значение.',
            'cover_image.image' => 'Картинка-превью должна быть изображением.',
            'cover_image.mimes' => 'Разрешены только JPG, PNG и WebP.',
            'cover_image.dimensions' => 'Размер изображения не должен превышать 6000×6000 пикселей.',
        ];
    }
}
