<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Masternode
 *
 * @property string $public_key
 * @property int    $height
 * @property string $ip
 * @property int    $last_won
 * @property int    $blacklist
 * @property int    $fails
 * @property int    $status
 */
final class Masternode extends Model
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
     * @var string
     */
    protected $table = 'masternode';
}
