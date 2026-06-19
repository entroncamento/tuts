<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Serviço Python (TUT'S RAG)
    |--------------------------------------------------------------------------
    |
    | URL do servidor FastAPI e token partilhado de comunicação interna.
    | Gerar o token com: python -c "import secrets; print(secrets.token_hex(32))"
    | e copiar o mesmo valor para o .env do Python (INTERNAL_TOKEN=...).
    |
    */

    'python' => [
        'url'            => env('PYTHON_API_URL', 'http://127.0.0.1:8001/perguntar'),
        'url_health'     => env('PYTHON_HEALTH_URL', 'http://rag:8001/health'),
        'internal_token' => env('PYTHON_INTERNAL_TOKEN'),
    ],

    'metrics' => [
        'token' => env('METRICS_TOKEN'),
    ],

    'api_registration' => [
        // Demo/dev/staging only. Keep false in production once real email delivery is configured.
        'auto_verify' => env('TUTS_AUTO_VERIFY_API_REGISTER', false),
    ],

    'rag' => [
        'base_url' => env('RAG_SERVICE_BASE_URL')
            ?: rtrim((string) preg_replace('#/perguntar/?$#', '', env('PYTHON_API_URL', 'http://127.0.0.1:8001/perguntar')), '/'),
        'internal_token' => env('RAG_SERVICE_INTERNAL_TOKEN') ?: env('PYTHON_INTERNAL_TOKEN'),
    ],
];
