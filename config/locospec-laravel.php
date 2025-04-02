<?php

// config for Locospec/LLCS
return [
    'paths' => [
        'locospec',
    ],

    'drivers' => [
        'database_connections' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the routing behavior for LLCS model actions
    |
    */
    'routing' => [
        // Route prefix for all LLCS endpoints (e.g., 'lcs' creates '/lcs/...')
        'prefix' => 'lcs',

        // Route name prefix for all LLCS routes (e.g., 'lcs.' creates 'lcs.model.action')
        'as' => 'lcs',

        // Middleware to apply to all LLCS routes
        'middleware' => ['api'],
    ],
    'logging' => [
        // Base log file name (RotatingFileHandler will add dates)
        'file_path' => base_path('storage/logs/locospec/engine.log'),

        // Number of days to keep log files before deletion
        'retention_days' => 7,

        // To Enable the query logs, logs will come with the query response under meta object
        'query_logs' => false,
    ],
    'cache_path' => base_path('storage/app/private')
];
