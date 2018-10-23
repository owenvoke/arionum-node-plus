<?php

return [

    /*
    |--------------------------------------------------------------------------
    | General Configuration
    |--------------------------------------------------------------------------
    */

    // Allow others to connect to the node api.
    'public_api'         => env('NODE_PUBLIC_API', true),

    // Hosts that are allowed to mine on this node if not a public API.
    'allowed_hosts'      => [
        '127.0.0.1',
    ],

    // To avoid any problems if other clones are made.
    'coin'               => env('NODE_COIN', 'arionum'),

    // Enable testnet mode for development.
    'testnet'            => env('NODE_TESTNET', false),

    // Block accepting transfers from the official blacklist
    'official_blacklist' => true,

];
