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
];
