<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'career_provider' => env('CAREER_AI_PROVIDER', 'groq'),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'summary_model' => env('SUMMARIZATION_MODEL', 'gpt-4o-mini'),
        'quiz_model' => env('QUIZ_MODEL', 'gpt-4o-mini'),
    ],
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'base_uri' => env('GROQ_BASE_URI', 'https://api.groq.com/openai/v1'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'api_version' => env('GEMINI_API_VERSION', 'v1'),
    ],
    'google' => [
        'project_id' => env('GOOGLE_PROJECT_ID'),
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'speech_api_key' => env('GOOGLE_SPEECH_API_KEY', 'AIzaSyA22gJxXc-jVXEf8RnZD9CamWnvYNrSPgk'),
        'speech_http_referrer' => env('GOOGLE_SPEECH_HTTP_REFERRER', env('APP_URL')),
    ],
    'whisper_model' => env('WHISPER_MODEL', 'whisper-1'),
];
