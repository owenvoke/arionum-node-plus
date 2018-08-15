<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Config
 *
 * @property string $cfg
 * @property string $val
 */
class Config extends Model
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
    protected $table = 'config';
}
