<?php

return [

    /*
    |--------------------------------------------------------------------------
    | General Configuration
    |--------------------------------------------------------------------------
    */

    // Allow others to connect to the node api.
    'public_api'                       => env('NODE_PUBLIC_API', true),

    // Hosts that are allowed to mine on this node if not a public API.
    'allowed_hosts'                    => [
        '127.0.0.1',
    ],

    // To avoid any problems if other clones are made.
    'coin'                             => env('NODE_COIN', 'arionum'),

    // Enable testnet mode for development.
    'testnet'                          => env('NODE_TESTNET', false),

    // Block accepting transfers from the official blacklist
    'official_blacklist'               => true,

    /*
    |--------------------------------------------------------------------------
    | Peer Configuration
    |--------------------------------------------------------------------------
    */

    // Maximum number of connected peers.
    'peer_maximum'                     => env('NODE_MAX_PEERS', 30),

    // The number of peers to broadcast each new transaction to.
    'peer_transaction_propagation'     => 5,

    // How many new peers to check from each peer.
    'peer_test_maximum'                => 5,

    /*
    |--------------------------------------------------------------------------
    | Mempool Configuration
    |--------------------------------------------------------------------------
    */

    // The maximum transactions to accept from a single peer.
    'mempool_max_peer_transactions'    => 100,

    // The maximum number of mempool transactions to be rebroadcast.
    'mempool_max_rebroadcast'          => 5000,

    /*
    |--------------------------------------------------------------------------
    | Sanity Configuration
    |--------------------------------------------------------------------------
    */

    // The number of blocks between rebroadcasting transactions.
    'sanity_rebroadcast_height'        => 30,

    // Recheck the last blocks on sanity.
    'sanity_recheck_blocks'            => 10,

    // The interval to run the sanity in seconds.
    'sanity_interval'                  => env('NODE_SANITY_INTERVAL', 900),

    // Enable setting a new hostname (should be used only if you want to change the hostname).
    'sanity_allow_hostname_change'     => false,

    // Rebroadcast local transactions when running sanity.
    'sanity_rebroadcast_locals'        => true,

    // Retrieve additional peers when running sanity.
    'sanity_retrieve_additional_peers' => true,

];
