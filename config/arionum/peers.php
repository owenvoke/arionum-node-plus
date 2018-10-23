<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Peer Configuration
    |--------------------------------------------------------------------------
    */

    // Maximum number of connected peers.
    'maximum'                 => env('NODE_MAX_PEERS', 30),

    // The number of peers to broadcast each new transaction to.
    'transaction_propagation' => 5,

    // How many new peers to check from each peer.
    'test_maximum'            => 5,

];
