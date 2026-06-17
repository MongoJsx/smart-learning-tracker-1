<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoogleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credential' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'credential.required' => 'กรุณาส่งโทเค็นจาก Google',
        ];
    }
}
