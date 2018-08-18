<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class Block
 *
 * @property string $id
 * @property string $generator
 * @property int    $height
 * @property Carbon $date
 * @property string $nonce
 * @property string $signature
 * @property string $difficulty
 * @property string $argon
 * @property int    $transactions
 */
final class Block extends Model
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
    protected $dates = [
        'date',
    ];
}
