<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sanity Configuration
    |--------------------------------------------------------------------------
    */

    // The number of blocks between rebroadcasting transactions.
    'rebroadcast_height'        => 30,

    // Recheck the last blocks on sanity.
    'recheck_blocks'            => 10,

    // The interval to run the sanity in seconds.
    'interval'                  => env('NODE_SANITY_INTERVAL', 900),

    // Enable setting a new hostname (should be used only if you want to change the hostname).
    'allow_hostname_change'     => false,

    // Rebroadcast local transactions when running sanity.
    'rebroadcast_locals'        => true,

    // Retrieve additional peers when running sanity.
    'retrieve_additional_peers' => true,

];
