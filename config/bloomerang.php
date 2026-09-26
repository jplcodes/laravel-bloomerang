<?php

return [

    // The API key sent as the X-API-KEY header for every request, unless a bearer token is supplied instead.
    'api_key' => env('BLOOMERANG_API_KEY'),

    // Bloomerang's API host; falls back to the public Bloomerang API when left empty.
    'base_url' => env('BLOOMERANG_BASE_URL'),

    // Timeouts used for interactive, user-facing calls that must fail fast.
    'request_mode' => [
        'timeout' => 4,
        'connect_timeout' => 2,
    ],

    // Timeouts and retry behaviour used for background jobs, which can afford to wait and retry.
    'job_mode' => [
        'timeout' => 30,
        'connect_timeout' => 5,
        'retries' => 3,
        'backoff_ms' => [1000, 2000, 4000],
        'jitter_ms' => 250,
        'max_retry_after' => 60,
    ],

    // Structured logging of every call the package makes.
    'logging' => [
        'enabled' => env('BLOOMERANG_LOGGING', true),
        'channel' => env('BLOOMERANG_LOG_CHANNEL'),
    ],

];
