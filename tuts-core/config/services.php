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
        'internal_token' => env('PYTHON_INTERNAL_TOKEN'),
    ],

];
