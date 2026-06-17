<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:12'],
            'room' => ['nullable', 'string', 'max:255'],
            'target_hours' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date', 'required_with:start_time,end_time'],
            'start_time' => ['nullable', 'regex:/^\\d{2}:\\d{2}(:\\d{2})?$/', 'required_with:end_time'],
            'end_time' => ['nullable', 'regex:/^\\d{2}:\\d{2}(:\\d{2})?$/'],
        ];

        if ($this->hasTableSafe('subjects') && $this->hasColumnSafe('subjects', 'semester_id') && $this->hasTableSafe('semester')) {
            $rules['semester_id'] = [
                // Auto-select current semester when omitted (handled in controller).
                'nullable',
                'integer',
                Rule::exists('semester', 'semester_id'),
            ];
        }

        return $rules;
    }

    private function hasTableSafe(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (Throwable $error) {
            $exists = false;
        }

        $cache[$table] = $exists;
        return $exists;
    }

    private function hasColumnSafe(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
                [$table, $column]
            ) !== null;
        } catch (Throwable $error) {
            $exists = false;
        }

        $cache[$key] = $exists;
        return $exists;
    }
}
