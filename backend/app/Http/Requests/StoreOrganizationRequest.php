<?php

namespace App\Http\Requests;

use App\Rules\YandexOrganizationUrl;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', 'url:http,https', new YandexOrganizationUrl],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'url.required' => 'Вставьте ссылку на организацию.',
            'url.string' => 'Ссылка должна быть строкой.',
            'url.max' => 'Ссылка слишком длинная.',
            'url.url' => 'Введите корректную HTTP- или HTTPS-ссылку.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Переданные данные некорректны.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
