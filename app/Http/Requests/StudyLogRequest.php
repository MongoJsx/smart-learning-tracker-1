<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudyLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'log_date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'mood' => ['nullable', 'string', 'max:50'],
            'log_type' => ['sometimes', 'string', 'in:study,document_summary,audio_summary'],
            'is_summary' => ['sometimes', 'boolean'],
        ];
    }
}
