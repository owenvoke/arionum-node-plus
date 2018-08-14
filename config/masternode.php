<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Masternode Mode Enabled
    |--------------------------------------------------------------------------
    |
    | Register this node as a masternode.
    |
    */

    'enabled'    => false,

    /*
    |--------------------------------------------------------------------------
    | Masternode Public Key
    |--------------------------------------------------------------------------
    |
    | The public key registered to this masternode.
    |
    */
    'public_key' => env('MASTERNODE_PUBLIC_KEY'),

];
