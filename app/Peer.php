<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class Peer
 *
 * @property int    $id
 * @property string $hostname
 * @property Carbon $blacklisted
 * @property Carbon $ping
 * @property bool   $reserve
 * @property string $ip
 * @property int    $fails
 * @property int    $stuckfail
 */
final class Peer extends Model
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
        'blacklisted',
        'ping',
    ];
}
