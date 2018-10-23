<?php

namespace App;

use App\Helpers\Key;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * @var array
     */
    public $appends = [
        'confirmations',
        'type',
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

    // reverse and remove all transactions from a block
    public function reverse($block)
    {
        global $db;

        $acc = new Account();
        $r = $db->run("SELECT * FROM transactions WHERE block=:block ORDER by `version` ASC", [":block" => $block]);
        foreach ($r as $x) {
            Log::info("Reversing transaction $x[id]", 4);
            if (empty($x['src'])) {
                $x['src'] = $acc->getAddress($x['public_key']);
            }
            if ($x['version'] == 2) {
                // payment sent to alias
                $rez = $db->run(
                    "UPDATE accounts SET balance=balance-:val WHERE alias=:alias",
                    [":alias" => $x['dst'], ":val" => $x['val']]
                );
                if ($rez != 1) {
                    Log::info("Update alias balance minus failed", 3);
                    return false;
                }
            } else {
                // other type of transactions

                if ($x['version'] != 100 && $x['version'] < 111) {
                    $rez = $db->run(
                        "UPDATE accounts SET balance=balance-:val WHERE id=:id",
                        [":id" => $x['dst'], ":val" => $x['val']]
                    );
                    if ($rez != 1) {
                        Log::info("Update accounts balance minus failed", 3);
                        return false;
                    }
                }
            }
            // on version 0 / reward transaction, don't credit anyone
            if ($x['version'] > 0 && $x['version'] < 111) {
                $rez = $db->run(
                    "UPDATE accounts SET balance=balance+:val WHERE id=:id",
                    [":id" => $x['src'], ":val" => $x['val'] + $x['fee']]
                );
                if ($rez != 1) {
                    Log::info('Update account balance plus failed');
                    return false;
                }
            }
            // removing the alias if the alias transaction is reversed
            if ($x['version'] == 3) {
                $rez = $db->run(
                    "UPDATE accounts SET alias=NULL WHERE id=:id",
                    [":id" => $x['src']]
                );
                if ($rez != 1) {
                    Log::info('Clear alias failed');
                    return false;
                }
            }


            if ($x['version'] >= 100 && $x['version'] < 110 && $x['height'] >= 80000) {
                if ($x['version'] == 100) {
                    $rez = $db->run(
                        "DELETE FROM masternode WHERE public_key=:public_key",
                        [':public_key' => $x['public_key']]
                    );
                    if ($rez != 1) {
                        Log::info("Delete from masternode failed", 3);
                        return false;
                    }
                } elseif ($x['version'] == 101) {
                    $rez = $db->run(
                        "UPDATE masternode SET status=1 WHERE public_key=:public_key",
                        [':public_key' => $x['public_key']]
                    );
                } elseif ($x['version'] == 102) {
                    $rez = $db->run(
                        "UPDATE masternode SET status=0 WHERE public_key=:public_key",
                        [':public_key' => $x['public_key']]
                    );
                } elseif ($x['version'] == 103) {
                    $mnt = $db->row(
                        "SELECT height, `message` FROM transactions WHERE version=100 AND public_key=:public_key ORDER by height DESC LIMIT 1",
                        [":public_key" => $x['public_key']]
                    );
                    $vers = $db->single(
                        "SELECT `version` FROM transactions WHERE (version=101 or version=102) AND public_key=:public_key AND height>:height ORDER by height DESC LIMIT 1",
                        [":public_key" => $x['public_key'], ":height" => $mnt['height']]
                    );

                    $status = 1;

                    if ($vers == 101) {
                        $status = 0;
                    }

                    $rez = $db->run(
                        "INSERT into masternode SET `public_key`=:public_key, `height`=:height, `ip`=:ip, `status`=:status",
                        [
                            ":public_key" => $x['public_key'],
                            ":height"     => $mnt['height'],
                            ":ip"         => $mnt['message'],
                            ":status"     => $status,
                        ]
                    );
                    if ($rez != 1) {
                        Log::info('Insert into masternode failed');
                        return false;
                    }
                    $rez = $db->run(
                        "UPDATE accounts SET balance=balance-100000 WHERE public_key=:public_key",
                        [':public_key' => $x['public_key']]
                    );
                    if ($rez != 1) {
                        Log::info('Update masternode balance failed');
                        return false;
                    }
                }
            }

            // internal masternode history
            if ($x['version'] === 111) {
                Log::info('Masternode reverse: '.$x['message']);
                $m = explode(",", $x['message']);

                $rez = $db->run(
                    "UPDATE masternode SET fails=:fails, blacklist=:blacklist, last_won=:last_won WHERE public_key=:public_key",
                    [":public_key" => $x['public_key'], ":blacklist" => $m[0], ":fails" => $m[2], ":last_won" => $m[1]]
                );
                if ($rez != 1) {
                    Log::info('Update masternode log failed');
                    return false;
                }
            }

            // add the transactions to mempool
            if ($x['version'] > 0 && $x['version'] <= 110) {
                $this->addMempool($x);
            }
            $res = $db->run("DELETE FROM transactions WHERE id=:id", [":id" => $x['id']]);
            if ($res != 1) {
                Log::info('Delete transaction failed');
                return false;
            }
        }
    }

    // returns X  transactions from mempool
    public function mempool($max)
    {
        global $db;
        $block = new Block();
        $current = $block->current();
        $height = $current['height'] + 1;
        // only get the transactions that are not locked with a future height
        $r = $db->run(
            "SELECT * FROM mempool WHERE height<=:height ORDER by val/fee DESC LIMIT :max",
            [":height" => $height, ":max" => $max + 50]
        );
        $transactions = [];
        if (count($r) > 0) {
            $i = 0;
            $balance = [];
            foreach ($r as $x) {
                $trans = [
                    "id"         => $x['id'],
                    "dst"        => $x['dst'],
                    "val"        => $x['val'],
                    "fee"        => $x['fee'],
                    "signature"  => $x['signature'],
                    "message"    => $x['message'],
                    "version"    => $x['version'],
                    "date"       => $x['date'],
                    "public_key" => $x['public_key'],
                ];

                if ($i >= $max) {
                    break;
                }

                if (empty($x['public_key'])) {
                    Log::info("$x[id] - Transaction has empty public_key");
                    continue;
                }
                if (empty($x['src'])) {
                    Log::info("$x[id] - Transaction has empty src");
                    continue;
                }
                if (!$this->check($trans, $current['height'])) {
                    Log::info("$x[id] - Transaction Check Failed");
                    continue;
                }

                $balance[$x['src']] += $x['val'] + $x['fee'];
                if ($db->single("SELECT COUNT(1) FROM transactions WHERE id=:id", [":id" => $x['id']]) > 0) {
                    Log::info("$x[id] - Duplicate transaction");
                    continue; //duplicate transaction
                }

                $res = $db->single(
                    "SELECT COUNT(1) FROM accounts WHERE id=:id AND balance>=:balance",
                    [":id" => $x['src'], ":balance" => $balance[$x['src']]]
                );

                if ($res == 0) {
                    Log::info("$x[id] - Not enough funds in balance");
                    continue; // not enough balance for the transactions
                }
                $i++;
                ksort($trans);
                $transactions[$x['id']] = $trans;
            }
        }
        // always sort the array
        ksort($transactions);

        return $transactions;
    }

    // add a new transaction to the blockchain
    public function add($block, $height, $x)
    {
        global $db;
        $acc = new Account();
        $acc->add($x['public_key'], $block);
        if ($x['version'] == 1) {
            $acc->addId($x['dst'], $block);
        }
        $x['id'] = san($x['id']);
        $bind = [
            ":id"         => $x['id'],
            ":public_key" => $x['public_key'],
            ":height"     => $height,
            ":block"      => $block,
            ":dst"        => $x['dst'],
            ":val"        => $x['val'],
            ":fee"        => $x['fee'],
            ":signature"  => $x['signature'],
            ":version"    => $x['version'],
            ":date"       => $x['date'],
            ":message"    => $x['message'],
        ];
        $res = $db->run(
            "INSERT into transactions SET id=:id, public_key=:public_key, block=:block,  height=:height, dst=:dst, val=:val, fee=:fee, signature=:signature, version=:version, message=:message, `date`=:date",
            $bind
        );
        if ($res != 1) {
            return false;
        }
        if ($x['version'] == 2 && $height >= 80000) {
            $db->run(
                "UPDATE accounts SET balance=balance+:val WHERE alias=:alias",
                [":alias" => $x['dst'], ":val" => $x['val']]
            );
        } elseif ($x['version'] == 100 && $height >= 80000) {
            //master node deposit
        } elseif ($x['version'] == 103 && $height >= 80000) {
            $blk = new Block();
            $blk->masternodeLog($x['public_key'], $height, $block);

            //master node withdrawal
        } else {
            $db->run(
                "UPDATE accounts SET balance=balance+:val WHERE id=:id",
                [":id" => $x['dst'], ":val" => $x['val']]
            );
        }


        // no debit when the transaction is reward
        if ($x['version'] > 0) {
            $db->run(
                "UPDATE accounts SET balance=(balance-:val)-:fee WHERE id=:id",
                [":id" => $x['src'], ":val" => $x['val'], ":fee" => $x['fee']]
            );
        }


        // set the alias
        if ($x['version'] == 3 && $height >= 80000) {
            $db->run(
                "UPDATE accounts SET alias=:alias WHERE id=:id",
                [":id" => $x['src'], ":alias" => $x['message']]
            );
        }


        if ($x['version'] >= 100 && $x['version'] < 110 && $height >= 80000) {
            $message = $x['message'];
            $message = preg_replace("/[^0-9\.]/", "", $message);
            if ($x['version'] == 100) {
                $db->run(
                    "INSERT into masternode SET `public_key`=:public_key, `height`=:height, `ip`=:ip, `status`=1",
                    [":public_key" => $x['public_key'], ":height" => $height, ":ip" => $message]
                );
            } else {
                if ($x['version'] == 101) {
                    $db->run(
                        "UPDATE masternode SET status=0 WHERE public_key=:public_key",
                        [':public_key' => $x['public_key']]
                    );
                } elseif ($x['version'] == 102) {
                    $db->run(
                        "UPDATE masternode SET status=1 WHERE public_key=:public_key",
                        [':public_key' => $x['public_key']]
                    );
                } elseif ($x['version'] == 103) {
                    $db->run(
                        "DELETE FROM masternode WHERE public_key=:public_key",
                        [':public_key' => $x['public_key']]
                    );
                    $db->run(
                        "UPDATE accounts SET balance=balance+100000 WHERE public_key=:public_key",
                        [':public_key' => $x['public_key']]
                    );
                }
            }
        }

        Mempool::delete($x['id']);
        return true;
    }

    // check the transaction for validity
    public function check($x, $height = 0)
    {
        // if no specific block, use current
        if ($height === 0) {
            $block = new Block();
            $current = $block->current();
            $height = $current['height'];
        }
        $acc = new Account();
        $info = $x['val']."-".$x['fee']."-".$x['dst']."-".$x['message']."-".$x['version']."-".$x['public_key']."-".$x['date'];

        // hard fork at 80000 to implement alias, new mining system, assets
        // if($x['version']>1 && $height<80000){
        //     return false;
        // }

        // internal transactions
        if ($x['version'] > 110) {
            return false;
        }

        // the value must be >=0
        if ($x['val'] < 0) {
            Log::info("$x[id] - Value below 0", 3);
            return false;
        }

        // the fee must be >=0
        if ($x['fee'] < 0) {
            Log::info("$x[id] - Fee below 0", 3);
            return false;
        }

        // the fee is 0.25%, hardcoded
        $fee = $x['val'] * 0.0025;
        $fee = number_format($fee, 8, ".", "");
        if ($fee < 0.00000001) {
            $fee = 0.00000001;
        }
        //alias fee
        if ($x['version'] == 3 && $height >= 80000) {
            $fee = 10;
            if (!$acc->freeAlias($x['message'])) {
                Log::info("Alias not free", 3);
                return false;
            }
            // alias can only be set once per account
            if ($acc->hasAlias($x['public_key'])) {
                Log::info("The account already has an alias", 3);
                return false;
            }
        }

        //masternode transactions

        if ($x['version'] >= 100 && $x['version'] < 110 && $height >= 80000) {
            if ($x['version'] == 100) {
                $message = $x['message'];
                $message = preg_replace("/[^0-9\.]/", "", $message);
                if (!filter_var($message, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    Log::info("The Masternode IP is invalid", 3);
                    return false;
                }
                global $db;
                $existing = $db->single(
                    "SELECT COUNT(1) FROM masternode WHERE public_key=:id or ip=:ip",
                    ["id" => $x['public_key'], ":ip" => $message]
                );
                if ($existing != 0) {
                    return false;
                }
            }


            if ($x['version'] == 100 && $x['val'] != 100000) {
                Log::info('The masternode transaction is not 100k');
                return false;
            } elseif ($x['version'] != 100) {
                $mn = $acc->getMasternode($x['public_key']);

                if (!$mn) {
                    Log::info('The masternode does not exist');
                    return false;
                }
                if ($x['version'] == 101 && $mn['status'] != 1) {
                    Log::info('The masternode does is not running');
                    return false;
                } elseif ($x['version'] == 102 && $mn['status'] != 0) {
                    Log::info('The masternode is not paused');
                    return false;
                } elseif ($x['version'] == 103) {
                    if ($mn['status'] != 0) {
                        Log::info('The masternode is not paused');
                        return false;
                    } elseif ($height - $mn['last_won'] < 10800) { //10800
                        Log::info('The masternode last won block is less than 10800 blocks');
                        return false;
                    } elseif ($height - $mn['height'] < 32400) { //32400
                        Log::info('The masternode start height is less than 32400 blocks! '.($height - $mn['height']));
                        return false;
                    }
                }
            }
        }


        // max fee after block 10800 is 10
        if ($height > 10800 && $fee > 10) {
            $fee = 10; //10800
        }
        // added fee does not match
        if ($fee != $x['fee']) {
            Log::info($x->id.' - Fee not 0.25%');
            Log::info(json_encode($x));
            return false;
        }

        if ($x['version'] == 1) {
            // invalid destination address
            if (!$acc->validAddress($x['dst'])) {
                Log::info($x['id'].' - Invalid destination address');
                return false;
            }
        } elseif ($x['version'] == 2 && $height >= 80000) {
            if (!$acc->validAlias($x['dst'])) {
                Log::info("$x[id] - Invalid destination alias", 3);
                return false;
            }
        }


        // reward transactions are not added via this function
        if ($x['version'] < 1) {
            Log::info("$x[id] - Invalid version <1", 3);
            return false;
        }
        //if($x['version']>1) { Log::info("$x[id] - Invalid version >1"); return false; }

        // public key must be at least 15 chars / probably should be replaced with the validator function
        if (strlen($x['public_key']) < 15) {
            Log::info("$x[id] - Invalid public key size", 3);
            return false;
        }
        // no transactions before the genesis
        if ($x['date'] < 1511725068) {
            Log::info("$x[id] - Date before genesis", 3);
            return false;
        }
        // no future transactions
        if ($x['date'] > time() + 86400) {
            Log::info("$x[id] - Date in the future", 3);
            return false;
        }
        // prevent the resending of broken base58 transactions
        if ($height > 16900 && $x['date'] < 1519327780) {
            Log::info("$x[id] - Broken base58 transaction", 3);
            return false;
        }
        $id = $this->hash($x);
        // the hash does not match our regenerated hash
        if ($x['id'] != $id) {
            // fix for broken base58 library which was used until block 16900, accepts hashes without the first 1 or 2 bytes
            $xs = base58_decode($x['id']);
            if (((strlen($xs) != 63 || substr($id, 1) != $x['id']) && (strlen($xs) != 62 || substr(
                $id,
                2
            ) != $x['id'])) || $height > 16900) {
                Log::info("$x[id] - $id - Invalid hash");
                return false;
            }
        }

        //verify the ecdsa signature
        if (!$acc->checkSignature($info, $x['signature'], $x['public_key'])) {
            Log::info("$x[id] - Invalid signature - $info");
            return false;
        }

        return true;
    }

    // sign a transaction
    public function sign($x, $private_key)
    {
        $info = $x['val']."-".$x['fee']."-".$x['dst']."-".$x['message']."-".$x['version']."-".$x['public_key']."-".$x['date'];

        $signature = ec_sign($info, $private_key);

        return $signature;
    }

    public function getConfirmationsAttribute(): int
    {
        return Block::current()->height - $this->height;
    }

    public function getTypeAttribute(): string
    {
        // Reward transactions
        if ($this->version === 0) {
            return 'mining';
        }

        // Normal transactions
        if ($this->version === 1) {
            return ($this->dst) ? 'credit' : 'debit';
        }

        return 'other';
    }
}
