<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class Transaction
 *
 * @property string $id
 * @property string $block
 * @property int    $height
 * @property string $dst
 * @property float  $val
 * @property float  $fee
 * @property string $signature
 * @property int    $version
 * @property string $message
 * @property Carbon $date
 * @property string $public_key
 */
final class Transaction extends Model
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
