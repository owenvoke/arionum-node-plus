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
 * @property int    $difficulty
 * @property string $argon
 * @property int    $transactions
 */
final class Block extends Model
{
    use Traits\Blockchain\HasGenesisBlock;

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

    /**
     * Retrieve the current block
     *
     * @return self
     */
    public static function current(): self
    {
        return static::query()->limit(1)->orderByDesc('height')->first();
    }
}
