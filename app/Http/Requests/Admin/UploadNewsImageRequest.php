<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadNewsImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=6000,max_height=6000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Выберите изображение.',
            'image.image' => 'Файл должен быть изображением.',
            'image.mimes' => 'Разрешены только JPG, PNG и WebP.',
            'image.max' => 'Размер изображения не должен превышать 5 МБ.',
            'image.dimensions' => 'Размер изображения не должен превышать 6000×6000 пикселей.',
        ];
    }
}
