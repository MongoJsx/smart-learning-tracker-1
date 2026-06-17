<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,txt,mp3,wav,m4a,jpg,jpeg,png,gif,webp'],
            'storage_path' => ['nullable', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'file_type' => ['nullable', 'in:pdf,word,audio,image,other'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->hasFile('file') && ! $this->filled('storage_path')) {
                $validator->errors()->add('file', 'ต้องส่งไฟล์หรือ storage_path อย่างน้อยหนึ่งรายการ');
            }
        });
    }
}
