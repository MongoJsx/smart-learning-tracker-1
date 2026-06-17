<?php

namespace App\Services\AI;

use OpenAI;
use OpenAI\Contracts\ClientContract;

class AIClientFactory
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function openAI(): ClientContract
    {
        return OpenAI::client($this->config['openai']['api_key'] ?? '');
    }

    public function groq(): ClientContract
    {
        return OpenAI::factory()
            ->withApiKey((string) ($this->config['groq']['api_key'] ?? ''))
            ->withBaseUri((string) ($this->config['groq']['base_uri'] ?? 'https://api.groq.com/openai/v1'))
            ->make();
    }

}
