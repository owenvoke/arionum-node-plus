<?php

namespace App;

use App\Helpers\Blacklist;
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
final class Mempool extends Model
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

    /**
     * @var array
     */
    public $appends = [
        'confirmations',
        'type',
    ];

    /**
     * Clear the mempool of transactions older than 1000 blocks
     * @return bool
     * @throws \Throwable
     */
    public static function clean()
    {
        $limit = Block::current()->height - 1000;

        return static::query()->where('height', '<', $limit)->delete();
    }

    /**
     * @return int
     */
    public function getConfirmationsAttribute(): int
    {
        return -1;
    }

    /**
     * @return string
     */
    public function getTypeAttribute(): string
    {
        return 'mempool';
    }

    // add a new transaction to mempool and lock it with the current height
    public function addMempool($x, $peer = "")
    {
        global $db;
        global $_config;
        $block = new Block();
        if ($x['version'] > 110) {
            return true;
        }

        if ($_config['use_official_blacklist'] !== false) {
            if (Blacklist::checkPublicKey($x['public_key']) || Blacklist::checkAddress($x['src'])) {
                return true;
            }
        }
        $current = $block->current();
        $height = $current['height'];
        $x['id'] = san($x['id']);
        $bind = [
            ":peer"      => $peer,
            ":id"        => $x['id'],
            "public_key" => $x['public_key'],
            ":height"    => $height,
            ":src"       => $x['src'],
            ":dst"       => $x['dst'],
            ":val"       => $x['val'],
            ":fee"       => $x['fee'],
            ":signature" => $x['signature'],
            ":version"   => $x['version'],
            ":date"      => $x['date'],
            ":message"   => $x['message'],
        ];

        //only a single masternode command of same type, per block
        if ($x['version'] >= 100 && $x['version'] < 110) {
            $check = $db->single(
                "SELECT COUNT(1) FROM mempool WHERE public_key=:public_key",
                [":public_key" => $x['public_key']]
            );
            if ($check != 0) {
                _log("Masternode transaction already in mempool", 3);
                return false;
            }
        }

        $db->run(
            "INSERT into mempool  SET peer=:peer, id=:id, public_key=:public_key, height=:height, src=:src, dst=:dst, val=:val, fee=:fee, signature=:signature, version=:version, message=:message, `date`=:date",
            $bind
        );
        return true;
    }
}
