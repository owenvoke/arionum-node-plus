<?php

namespace App;

use App\Helpers\Key;
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

    /**
     * Retrieve a hash of the transactions most important fields and create the id
     * @return string
     */
    public function hash()
    {
        $info = [
            'val'        => $this->val,
            'fee'        => $this->fee,
            'dst'        => $this->dst,
            'message'    => $this->message,
            'version'    => $this->version,
            'public_key' => $this->public_key,
            'date'       => $this->date,
            'signature'  => $this->signature,
        ];

        $hash = hash('sha512', implode('-', $info));
        return Key::hexadecimalToAroBase58($hash);
    }
}
