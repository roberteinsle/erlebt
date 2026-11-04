<?php

return [
    // Harte Kostenbremse: maximale Durchläufe pro Tag für die gesamte Instanz.
    'max_runs_per_day' => (int) env('MAX_RUNS_PER_DAY', 20),

    // Einziger Login dieser Instanz, wird per `php artisan db:seed --force` angelegt.
    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'models' => [
        'questions' => env('MODEL_QUESTIONS', 'anthropic/claude-haiku-5.5'),
        'review' => env('MODEL_REVIEW', 'anthropic/claude-sonnet-5.5'),
    ],

    'source_url' => env('SOURCE_URL', 'https://github.com/roberteinsle/erlebt'),
];
