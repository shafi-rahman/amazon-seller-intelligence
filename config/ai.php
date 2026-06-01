<?php

return [

    'providers' => [

        'anthropic' => [
            'api_key'     => env('ANTHROPIC_API_KEY'),
            'model'       => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
            'max_tokens'  => 4096,
            'api_url'     => 'https://api.anthropic.com/v1/messages',
            'version'     => '2023-06-01',
        ],

        'openai' => [
            'api_key'         => env('OPENAI_API_KEY'),
            'model'           => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'api_url'         => 'https://api.openai.com/v1',
        ],

        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'model'   => 'llama-3.1-8b-instant',
            'api_url' => 'https://api.groq.com/openai/v1',
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model'   => 'gemini-1.5-flash',
        ],

        'ollama' => [
            'base_url'        => env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'),
            'embedding_model' => 'nomic-embed-text',
        ],

    ],

    // Primary provider for reasoning tasks
    'default_provider' => 'anthropic',

    // Provider used for embeddings
    'embedding_provider' => env('APP_ENV') === 'local' && empty(env('OPENAI_API_KEY'))
        ? 'ollama'
        : 'openai',

];
