<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Account
 *
 * @property string $id
 * @property string $public_key
 * @property string $block
 * @property float  $balance
 * @property string $alias
 */
class Account extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;
    /**
     * @var bool
     */
    public $timestamps = false;
    /**
     * @var array
     */
    protected $casts = [
        'balance' => 'float',
    ];
}
