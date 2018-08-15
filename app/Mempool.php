<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class Mempool
 *
 * @property string $id
 * @property int    $height
 * @property string $src
 * @property string $dst
 * @property float  $val
 * @property float  $fee
 * @property string $signature
 * @property int    $version
 * @property string $message
 * @property string $public_key
 * @property Carbon $date
 * @property string $peer
 */
class Mempool extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;
    /**
     * @var string
     */
    protected $table = 'mempool';
    /**
     * @var bool
     */
    public $timestamps = false;
    /**
     * @var array
     */
    protected $casts = [
        'val' => 'float',
        'fee' => 'float',
    ];
    /**
     * @var array
     */
    protected $dates = [
        'date',
    ];
}
