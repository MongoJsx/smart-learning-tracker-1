<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuizGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'question_types' => ['nullable', 'array'],
            'question_types.*' => ['in:multiple_choice,true_false,short_answer'],
            'question_count' => ['nullable', 'integer', 'min:3', 'max:50'],
        ];
    }
}
