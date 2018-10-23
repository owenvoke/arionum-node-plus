<?php

namespace App;

use App\Helpers\EllipticCurve;
use App\Helpers\Key;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * @throws \Throwable
     */
    public static function current(): self
    {
        return static::query()->limit(1)->orderByDesc('height')->first();
    }

    public function add(
        int $height,
        string $publicKey,
        string $nonce,
        $data,
        Carbon $date,
        $signature,
        $difficulty,
        $rewardSignature,
        $argon,
        $bootstrapping = false
    ) {
        $acc = new Account();
        $trx = new Transaction();

        $generator = $acc->getAddress($publicKey);

        // the transactions are always sorted in the same way, on all nodes, as they are hashed as json
        ksort($data);

        // create the hash / block id
        $hash = $this->hash($generator, $height, $date, $nonce, $data, $signature, $difficulty, $argon);
        //fix for the broken base58 library used until block 16900, trimming the first 0 bytes.
        if ($height < 16900) {
            $hash = ltrim($hash, '1');
        }

        $json = json_encode($data);

        // create the block data and check it against the signature
        $info = "{$generator}-{$height}-{$date}-{$nonce}-{$json}-{$difficulty}-{$argon}";
        // _log($info,3);
        if (!$bootstrapping) {
            if (!$acc->checkSignature($info, $signature, $publicKey)) {
                Log::info('Block signature check failed');

                return false;
            }

            if (!$this->parseBlock($hash, $height, $data, true)) {
                Log::info('Parse block failed');

                return false;
            }
        }

        $reward = $this->reward($height, $data);

        $msg = '';

        if ($height >= 80458) {
            //reward the masternode

            $masternodeWinner = Masternode::query()
                ->where('status', 1)
                ->where('blacklist', '<', $height)
                ->where('height', '<', $height - 360)
                ->orderBy('last_won')
                ->orderBy('public_key')
                ->limit(1)
                ->value('public_key');

            Log::info('MN Winner: '.$masternodeWinner);

            if ($masternodeWinner) {
                $masternodeReward = round(0.33 * $reward, 8);
                $reward = round($reward - $masternodeReward, 8);
                $reward = number_format($reward, 8, ".", "");
                $masternodeReward = number_format($masternodeReward, 8, ".", "");
                Log::info('MN Reward: '.$masternodeReward);
            }
        }

        // the reward transaction
        $transaction = Transaction::make([
            'src'        => $generator,
            'dst'        => $generator,
            'val'        => $reward,
            'version'    => 0,
            'date'       => $date,
            'message'    => $msg,
            'fee'        => '0.00000000',
            'public_key' => $publicKey,
        ]);

        $transaction->signature = $rewardSignature;

        // Hash the transaction
        $transaction['id'] = $trx->hash($transaction);
        if (!$bootstrapping) {
            // check the signature
            $info = $transaction['val']."-".$transaction['fee']."-".$transaction['dst']."-".$transaction['message']."-".$transaction['version']."-".$transaction['public_key']."-".$transaction['date'];
            if (!$acc->checkSignature($info, $rewardSignature, $publicKey)) {
                Log::info('Reward signature failed');
                return false;
            }
        }

        // Insert the block into the db
        $total = count($data);

        $bind = [
            ":id"           => $hash,
            ":generator"    => $generator,
            ":signature"    => $signature,
            ":height"       => $height,
            ":date"         => $date,
            ":nonce"        => $nonce,
            ":difficulty"   => $difficulty,
            ":argon"        => $argon,
            ":transactions" => $total,
        ];
        $res = $db->run(
            "INSERT into blocks SET id=:id, generator=:generator, height=:height,`date`=:date,nonce=:nonce, signature=:signature, difficulty=:difficulty, argon=:argon, transactions=:transactions",
            $bind
        );
        if ($res != 1) {
            // rollback and exit if it fails
            _log("Block DB insert failed");
            $db->rollback();
            $db->exec("UNLOCK TABLES");
            return false;
        }

        // insert the reward transaction in the db
        $res = $trx->add($hash, $height, $transaction);
        if ($res == false) {
            // rollback and exit if it fails
            _log("Reward DB insert failed");
            $db->rollback();
            $db->exec("UNLOCK TABLES");
            return false;
        }
        if ($mn_winner !== false && $height >= 80458 && $masternodeReward > 0) {
            $db->run("UPDATE accounts SET balance=balance+:bal WHERE public_key=:pub",
                [":pub" => $mn_winner, ":bal" => $masternodeReward]);
            $bind = [
                ":id"         => hex2coin(hash("sha512", "mn".$hash.$height.$mn_winner)),
                ":public_key" => $publicKey,
                ":height"     => $height,
                ":block"      => $hash,
                ":dst"        => $acc->getAddress($mn_winner),
                ":val"        => $masternodeReward,
                ":fee"        => 0,
                ":signature"  => $rewardSignature,
                ":version"    => 0,
                ":date"       => $date,
                ":message"    => 'masternode',
            ];
            $res = $db->run(
                "INSERT into transactions SET id=:id, public_key=:public_key, block=:block,  height=:height, dst=:dst, val=:val, fee=:fee, signature=:signature, version=:version, message=:message, `date`=:date",
                $bind
            );
            if ($res != 1) {
                // rollback and exit if it fails
                _log("Masternode reward DB insert failed");
                $db->rollback();
                $db->exec("UNLOCK TABLES");
                return false;
            }
            $res = $this->reset_fails_masternodes($mn_winner, $height, $hash);
            if (!$res) {

                // rollback and exit if it fails
                _log("Masternode log DB insert failed");
                $db->rollback();
                $db->exec("UNLOCK TABLES");
                return false;
            }
        }

        // parse the block's transactions and insert them to db
        $res = $this->parseBlock($hash, $height, $data, false, $bootstrapping);

        if (($height - 1) % 3 == 2 && $height >= 80000 && $height < 80458) {
            $this->blacklist_masternodes();
            $this->reset_fails_masternodes($publicKey, $height, $hash);
        }
        // if any fails, rollback
        if ($res == false) {
            $db->rollback();
        } else {
            $db->commit();
        }
        // relese the locking as everything is finished
        $db->exec("UNLOCK TABLES");
        return true;
    }

    // resets the number of fails when winning a block and marks it with a transaction

    public function reset_fails_masternodes($public_key, $height, $hash)
    {
        global $db;
        $res = $this->masternode_log($public_key, $height, $hash);
        if ($res === 5) {
            return false;
        }

        if ($res) {
            $rez = $db->run("UPDATE masternode SET last_won=:last_won,fails=0 WHERE public_key=:public_key",
                [":public_key" => $public_key, ":last_won" => $height]);
            if ($rez != 1) {
                return false;
            }
        }
        return true;
    }

    //logs the current masternode status
    public function masternode_log($public_key, $height, $hash)
    {
        global $db;

        $mn = $db->row("SELECT blacklist,last_won,fails FROM masternode WHERE public_key=:public_key",
            [":public_key" => $public_key]);

        if (!$mn) {
            return false;
        }

        $id = hex2coin(hash("sha512", "resetfails-$hash-$height-$public_key"));
        $msg = "$mn[blacklist],$mn[last_won],$mn[fails]";

        $res = $db->run(

            "INSERT into transactions SET id=:id, block=:block, height=:height, dst=:dst, val=0, fee=0, signature=:sig, version=111, message=:msg, date=:date, public_key=:public_key",
            [
                ":id"         => $id,
                ":block"      => $hash,
                ":height"     => $height,
                ":dst"        => $hash,
                ":sig"        => $hash,
                ":msg"        => $msg,
                ":date"       => time(),
                ":public_key" => $public_key,
            ]

        );
        if ($res != 1) {
            return 5;
        }
        return true;
    }

    // returns the previous block
    public function prev()
    {
        global $db;
        $current = $db->row("SELECT * FROM blocks ORDER by height DESC LIMIT 1,1");

        return $current;
    }

    // calculates the difficulty / base target for a specific block. The higher the difficulty number, the easier it is to win a block.
    public function difficulty($height = 0)
    {
        global $db;

        // if no block height is specified, use the current block.
        if ($height == 0) {
            $current = $this->current();
        } else {
            $current = $this->get($height);
        }


        $height = $current['height'];

        if ($height == 10801 || ($height >= 80456 && $height < 80460)) {
            return "5555555555"; //hard fork 10900 resistance, force new difficulty
        }

        // last 20 blocks used to check the block times
        $limit = 20;
        if ($height < 20) {
            $limit = $height - 1;
        }

        // for the first 10 blocks, use the genesis difficulty
        if ($height < 10) {
            return $current['difficulty'];
        }

        // before mnn hf
        if ($height < 80000) {
            // elapsed time between the last 20 blocks
            $first = $db->row("SELECT `date` FROM blocks  ORDER by height DESC LIMIT :limit,1", [":limit" => $limit]);
            $time = $current['date'] - $first['date'];

            // avg block time
            $result = ceil($time / $limit);
            _log("Block time: $result", 3);


            // if larger than 200 sec, increase by 5%
            if ($result > 220) {
                $dif = bcmul($current['difficulty'], 1.05);
            } elseif ($result < 260) {
                // if lower, decrease by 5%
                $dif = bcmul($current['difficulty'], 0.95);
            } else {
                // keep current difficulty
                $dif = $current['difficulty'];
            }
        } elseif ($height >= 80458) {
            $type = $height % 2;
            $current = $db->row("SELECT difficulty from blocks WHERE height<=:h ORDER by height DESC LIMIT 1,1",
                [":h" => $height]);
            $blks = 0;
            $total_time = 0;
            $blk = $db->run("SELECT `date`, height FROM blocks WHERE height<=:h  ORDER by height DESC LIMIT 20",
                [":h" => $height]);
            for ($i = 0; $i < 19; $i++) {
                $ctype = $blk[$i + 1]['height'] % 2;
                $time = $blk[$i]['date'] - $blk[$i + 1]['date'];
                if ($type != $ctype) {
                    continue;
                }
                $blks++;
                $total_time += $time;
            }
            $result = ceil($total_time / $blks);
            _log("Block time: $result", 3);
            if ($result > 260) {
                $dif = bcmul($current['difficulty'], 1.05);
            } elseif ($result < 220) {
                // if lower, decrease by 5%
                $dif = bcmul($current['difficulty'], 0.95);
            } else {
                // keep current difficulty
                $dif = $current['difficulty'];
            }
        } else {
            // hardfork 80000, fix difficulty targetting


            $type = $height % 3;
            // for mn, we use gpu diff
            if ($type == 2) {
                return $current['difficulty'];
            }

            $blks = 0;
            $total_time = 0;
            $blk = $db->run("SELECT `date`, height FROM blocks  ORDER by height DESC LIMIT 60");
            for ($i = 0; $i < 59; $i++) {
                $ctype = $blk[$i + 1]['height'] % 3;
                $time = $blk[$i]['date'] - $blk[$i + 1]['date'];
                if ($type != $ctype) {
                    continue;
                }
                $blks++;
                $total_time += $time;
            }
            $result = ceil($total_time / $blks);
            _log("Block time: $result", 3);

            // if larger than 260 sec, increase by 5%
            if ($result > 260) {
                $dif = bcmul($current['difficulty'], 1.05);
            } elseif ($result < 220) {
                // if lower, decrease by 5%
                $dif = bcmul($current['difficulty'], 0.95);
            } else {
                // keep current difficulty
                $dif = $current['difficulty'];
            }
        }


        if (strpos($dif, '.') !== false) {
            $dif = substr($dif, 0, strpos($dif, '.'));
        }

        //minimum and maximum diff
        if ($dif < 1000) {
            $dif = 1000;
        }
        if ($dif > 9223372036854775800) {
            $dif = 9223372036854775800;
        }
        _log("Difficulty: $dif", 3);
        return $dif;
    }

    // calculates the maximum block size and increase by 10% the number of transactions if > 100 on the last 100 blocks
    public function maxTransactions()
    {
        $limit = $this->current()->height - 100;

        $average = self::query()->where('height', '>', $limit)->average('transactions');
        return ($average < 100) ? 100 : ceil($average * 1.1);
    }

    // Calculate the reward for each block
    public function reward($data = [])
    {
        // Starting reward
        $reward = 1000;

        // Decrease by 1% each 10800 blocks (approx 1 month)
        $factor = floor($this->height / 10800) / 100;
        $reward -= $reward * $factor;
        if ($reward < 0) {
            $reward = 0;
        }

        // Calculate the transaction fees
        $fees = 0;
        if (count($data) > 0) {
            foreach ($data as $x) {
                $fees += $x['fee'];
            }
        }

        return number_format($reward + $fees, 8, '.', '');
    }

    // checks the validity of a block
    public function check()
    {
        // argon must have at least 20 chars
        if (strlen($this->argon) < 20) {
            Log::info('Invalid block argon - '.$this->argon);
            return false;
        }
        $acc = new Account();

        if ($this->date > time() + 30) {
            _log('Future block - '.$this->date.' '.$this->generator, 2);
            return false;
        }

        // generator's public key must be valid
        if (!$acc->validKey($data['public_key'])) {
            _log("Invalid public key - $data[public_key]");
            return false;
        }

        //difficulty should be the same as our calculation
        if ($data['difficulty'] != $this->difficulty()) {
            _log("Invalid difficulty - $data[difficulty] - ".$this->difficulty());
            return false;
        }

        //check the argon hash and the nonce to produce a valid block
        if (!$this->mine($data['public_key'], $data['nonce'], $data['argon'], $data['difficulty'], 0, 0,
            $data['date'])) {
            _log("Mine check failed");
            return false;
        }

        return true;
    }

    // creates a new block on this node
    public function forge($nonce, $argon, $public_key, $private_key)
    {
        //check the argon hash and the nonce to produce a valid block
        if (!$this->mine($public_key, $nonce, $argon)) {
            _log("Forge failed - Invalid argon");
            return false;
        }

        // the block's date timestamp must be bigger than the last block
        $current = $this->current();
        $height = $current['height'] += 1;
        $date = time();
        if ($date <= $current['date']) {
            _log("Forge failed - Date older than last block");
            return false;
        }

        // get the mempool transactions
        $txn = new Transaction();
        $data = $txn->mempool($this->maxTransactions());


        $difficulty = $this->difficulty();
        $acc = new Account();
        $generator = $acc->getAddress($public_key);

        // always sort  the transactions in the same way
        ksort($data);

        // sign the block
        $signature = $this->sign($generator, $height, $date, $nonce, $data, $private_key, $difficulty, $argon);

        // reward transaction and signature
        $reward = $this->reward($height, $data);

        if ($height >= 80458) {
            //reward the masternode
            global $db;
            $mn_winner = $db->single(
                "SELECT public_key FROM masternode WHERE status=1 AND blacklist<:current AND height<:start ORDER by last_won ASC, public_key ASC LIMIT 1",
                [":current" => $height, ":start" => $height - 360]
            );
            _log("MN Winner: $mn_winner", 2);
            if ($mn_winner !== false) {
                $mn_reward = round(0.33 * $reward, 8);
                $reward = round($reward - $mn_reward, 8);
                $reward = number_format($reward, 8, ".", "");
                $mn_reward = number_format($mn_reward, 8, ".", "");
                _log("MN Reward: $mn_reward", 2);
            }
        }

        $msg = '';
        $transaction = [
            "src"        => $generator,
            "dst"        => $generator,
            "val"        => $reward,
            "version"    => 0,
            "date"       => $date,
            "message"    => $msg,
            "fee"        => "0.00000000",
            "public_key" => $public_key,
        ];
        ksort($transaction);
        $reward_signature = $txn->sign($transaction, $private_key);

        // add the block to the blockchain
        $res = $this->add(
            $height,
            $public_key,
            $nonce,
            $data,
            $date,
            $signature,
            $difficulty,
            $reward_signature,
            $argon
        );
        if (!$res) {
            _log("Forge failed - Block->Add() failed");
            return false;
        }
        return true;
    }

    public function blacklist_masternodes()
    {
        global $db;
        _log("Checking if there are masternodes to be blacklisted", 2);
        $current = $this->current();
        if (($current['height'] - 1) % 3 != 2) {
            _log("bad height");
            return;
        }
        $last = $this->get($current['height'] - 1);
        $total_time = $current['date'] - $last['date'];
        _log("blacklist total time $total_time");
        if ($total_time <= 600 && $current['height'] < 80500) {
            return;
        }
        if ($current['height'] >= 80500 && $total_time < 360) {
            return false;
        }
        if ($current['height'] >= 80500) {
            $total_time -= 360;
            $tem = floor($total_time / 120) + 1;
            if ($tem > 5) {
                $tem = 5;
            }
        } else {
            $tem = floor($total_time / 600);
        }

        Log::info("We have masternodes to blacklist - $tem", 2);

        $ban = $db->run(
            "SELECT public_key, blacklist, fails, last_won FROM masternode WHERE status=1 AND blacklist<:current AND height<:start ORDER by last_won ASC, public_key ASC LIMIT 0,:limit",
            [":current" => $last['height'], ":start" => $last['height'] - 360, ":limit" => $tem]
        );

        Log::info(json_encode($ban));

        $i = 0;
        foreach ($ban as $b) {
            $this->masternode_log($b['public_key'], $current['height'], $current['id']);
            _log("Blacklisting masternode - $i $b[public_key]", 2);
            $btime = 10;
            if ($current['height'] > 83000) {
                $btime = 360;
            }
            $db->run("UPDATE masternode SET fails=fails+1, blacklist=:blacklist WHERE public_key=:public_key",
                [":public_key" => $b['public_key'], ":blacklist" => $current['height'] + (($b['fails'] + 1) * $btime)]);
            $i++;
        }
    }

    // check if the arguments are good for mining a specific block
    public function mine($public_key, $nonce, $argon, $difficulty = 0, $current_id = 0, $current_height = 0, $time = 0)
    {
        // Invalid future blocks
        if ($time > time() + 30) {
            return false;
        }


        // If no id is specified, we use the current
        if ($current_id === 0 || $current_height === 0) {
            $current = $this->current();
            $current_id = $current['id'];
            $current_height = $current['height'];
        }

        Log::info('Block Timestamp '.$time);

        if ($time == 0) {
            $time = time();
        }
        // get the current difficulty if empty
        if ($difficulty === 0) {
            $difficulty = $this->difficulty();
        }

        if (empty($public_key)) {
            Log::info('Empty public key', 1);
            return false;
        }

        if ($current_height < 80000) {

            // the argon parameters are hardcoded to avoid any exploits
            if ($current_height > 10800) {
                Log::info('Block below 80000 but after 10800, using 512MB argon');
                $argon = '$argon2i$v=19$m=524288,t=1,p=1'.$argon; //10800 block hard fork - resistance against gpu
            } else {
                Log::info('Block below 10800, using 16MB argon');
                $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
            }
        } elseif ($current_height >= 80458) {
            if ($current_height % 2 == 0) {
                // cpu mining
                Log::info("CPU Mining - $current_height", 2);
                $argon = '$argon2i$v=19$m=524288,t=1,p=1'.$argon;
            } else {
                // gpu mining
                Log::info("GPU Mining - $current_height", 2);
                $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
            }
        } else {
            Log::info("Block > 80000 - $current_height", 2);
            if ($current_height % 3 == 0) {
                // cpu mining
                Log::info("CPU Mining - $current_height", 2);
                $argon = '$argon2i$v=19$m=524288,t=1,p=1'.$argon;
            } elseif ($current_height % 3 == 1) {
                // gpu mining
                Log::info("GPU Mining - $current_height", 2);
                $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
            } else {
                Log::info("Masternode Mining - $current_height", 2);
                // masternode
                global $db;

                // fake time
                if ($time > time()) {
                    Log::info("Masternode block in the future - $time", 1);
                    return false;
                }

                // selecting the masternode winner in order
                $winner = $db->single(
                    "SELECT public_key FROM masternode WHERE status=1 AND blacklist<:current AND height<:start ORDER by last_won ASC, public_key ASC LIMIT 1",
                    [":current" => $current_height, ":start" => $current_height - 360]
                );

                // if there are no active masternodes, give the block to gpu
                if ($winner === false) {
                    Log::info("No active masternodes, reverting to gpu", 1);
                    $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
                } else {
                    Log::info("The first masternode winner should be $winner", 1);

                    // 4 mins need to pass since last block
                    $last_time = $db->single("SELECT `date` FROM blocks WHERE height=:height",
                        [":height" => $current_height]);
                    if ($time - $last_time < 240 && !config('arionum.app.testnet')) {
                        Log::info("4 minutes have not passed since the last block - $time", 1);
                        return false;
                    }

                    if ($public_key == $winner) {
                        return true;
                    }

                    // If 10 mins have passed, try to give the block to the next masternode and do this every 10mins
                    Log::info("Last block time: $last_time, difference: ".($time - $last_time), 3);
                    if (($time - $last_time > 600 && $current_height < 80500) || ($time - $last_time > 360 && $current_height >= 80500)) {
                        Log::info('Current public_key '.$public_key, 3);
                        if ($current_height >= 80500) {
                            $total_time = $time - $last_time;
                            $total_time -= 360;
                            $tem = floor($total_time / 120) + 1;
                        } else {
                            $tem = floor(($time - $last_time) / 600);
                        }

                        $winner = $db->single(
                            "SELECT public_key FROM masternode WHERE status=1 AND blacklist<:current AND height<:start ORDER by last_won ASC, public_key ASC LIMIT :tem,1",
                            [":current" => $current_height, ":start" => $current_height - 360, ":tem" => $tem]
                        );

                        Log::info('Moving to the next masternode - '.$tem.' - '.$winner);

                        // if all masternodes are dead, give the block to gpu
                        if ($winner === false || ($tem >= 5 && $current_height >= 80500)) {
                            Log::info('All masternodes failed, giving the block to gpu');
                            $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
                        } elseif ($winner == $public_key) {
                            return true;
                        }

                        return false;
                    }

                    Log::info("A different masternode should win this block $public_key - $winner", 2);
                    return false;
                }
            }
        }

        // The hash base for agon
        $base = "$public_key-$nonce-".$current_id."-$difficulty";


        // Check argon's hash validity
        if (!password_verify($base, $argon)) {
            Log::info("Argon verify failed - $base - $argon", 2);
            return false;
        }

        // All nonces are valid in testnet
        if (config('arionum.app.testnet')) {
            return true;
        }

        // Prepare the base for the hashing
        $hash = $base.$argon;

        // Hash the base 6 times
        for ($i = 0; $i < 5;
             $i++) {
            $hash = hash("sha512", $hash, true);
        }
        $hash = hash("sha512", $hash);

        // Split it in 2 char substrings, to be used as hex
        $m = str_split($hash, 2);

        // Calculate a number based on 8 hex numbers
        // No specific reason, we just needed an algorithm to generate the number from the hash
        $duration = hexdec($m[10]).
            hexdec($m[15]).
            hexdec($m[20]).
            hexdec($m[23]).
            hexdec($m[31]).
            hexdec($m[40]).
            hexdec($m[45]).
            hexdec($m[55]);

        // The number must not start with 0
        $duration = ltrim($duration, '0');

        // Divide the number by the difficulty and create the deadline
        $result = gmp_div($duration, $difficulty);

        // If the deadline >0 and <=240, the arguments are valid fora  block win
        return ($result > 0 && $result <= 240);
    }

    // Parse the block transactions
    public function parseBlock($block, $height, array $data, $test = true, $bootstrapping = false)
    {
        // Data must be array
        if (!$data) {
            Log::info('Block data is false');

            return false;
        }

        $account = new Account();
        $transaction = new Transaction();

        // No transactions means all are valid
        if (count($data) == 0) {
            return true;
        }

        // Check if the number of transactions is not bigger than current block size
        $max = $this->maxTransactions();

        if (count($data) > $max) {
            Log::info('Too many transactions in block');
            return false;
        }

        $balance = [];
        $mns = [];

        foreach ($data as &$x) {
            // Get the sender's account if empty
            if (empty($x['src'])) {
                $x['src'] = $account->getAddress($x['public_key']);
            }
            if (!$bootstrapping) {
                // Validate the transaction
                if (!$transaction->check($x, $height)) {
                    Log::info('Transaction check failed - '.$x['id']);
                    return false;
                }
                if ($x['version'] >= 100 && $x['version'] < 110) {
                    $mns[] = $x['public_key'];
                }


                // prepare total balance
                $balance[$x['src']] += $x['val'] + $x['fee'];

                // Check if the transaction is already on the blockchain
                if (Transaction::query()->find($x['id'])->exists()) {
                    Log::info('Transaction already on the blockchain - '.$x['id']);
                    return false;
                }
            }
        }

        // Only a single masternode transaction per block for any masternode
        if (count($mns) != count(array_unique($mns))) {
            Log::info('Too many masternode transactions');
            return false;
        }

        if (!$bootstrapping) {
            // Check if the account has enough balance to perform the transaction
            foreach ($balance as $id => $bal) {
                $res = $db->single(
                    "SELECT COUNT(1) FROM accounts WHERE id=:id AND balance>=:balance",
                    [":id" => $id, ":balance" => $bal]
                );
                if ($res == 0) {
                    Log::info('Not enough balance for transaction - '.$id);
                    return false; // not enough balance for the transactions
                }
            }
        }
        // if the test argument is false, add the transactions to the blockchain
        if ($test == false) {
            foreach ($data as $d) {
                $res = $transaction->add($block, $height, $d);
                if ($res == false) {
                    return false;
                }
            }
        }

        return true;
    }

    // Delete the last X blocks
    public function pop($blocksToRemove = 1)
    {
        $current = $this->current();

        $this->deleteGreaterThan($current['height'] - $blocksToRemove + 1);
    }

    // Delete all blocks >= height
    public function deleteGreaterThan($height)
    {
        if ($height < 2) {
            $height = 2;
        }

        $trx = new Transaction();

        $blocks = Block::query()->where('height', '>=', $height)->orderByDesc('height')->get();

        if ($blocks->isEmpty()) {
            return false;
        }

        DB::raw('LOCK TABLES blocks WRITE, accounts WRITE, transactions WRITE, mempool WRITE, masternode WRITE');

        foreach ($blocks as $block) {
            $res = $trx->reverse($block->id);
            if ($res === false) {
                Log::info('A transaction could not be reversed. Delete block failed.');
                DB::raw('UNLOCK TABLES');

                return false;
            }

            if (!$block->delete()) {
                Log::info('Delete block failed.');
                DB::raw('UNLOCK TABLES');

                return false;
            }
        }

        DB::raw('UNLOCK TABLES');
        return true;
    }

    // sign a new block, used when mining
    public function sign($generator, $height, $date, $nonce, $data, $key, $difficulty, $argon)
    {
        $json = json_encode($data);
        $info = "{$generator}-{$height}-{$date}-{$nonce}-{$json}-{$difficulty}-{$argon}";

        $signature = EllipticCurve::sign($info, $key);
        return $signature;
    }

    // Generate the sha512 hash of the block data and converts it to base58
    public function hash($public_key, $height, $date, $nonce, $data, $signature, $difficulty, $argon)
    {
        $json = json_encode($data);
        $hash = hash("sha512", "{$public_key}-{$height}-{$date}-{$nonce}-{$json}-{$signature}-{$difficulty}-{$argon}");

        return Key::hexadecimalToAroBase58($hash);
    }


    // Exports the block data, to be used when submitting to other peers
    public function export($id = "", $height = "")
    {
        if (empty($id) && empty($height)) {
            return false;
        }

        $block = !empty($height) ? Block::findByHeight($height) : Block::find($id);

        if (!$block) {
            return false;
        }

        $transactions = Transaction::query()->where('version', '>', 0)->where('block', $block->id)->get();
        $block->data = $transactions;

        /** @var Transaction $generator */
        $generator = Transaction::query()
            ->where('version', 0)
            ->where('block', $block->id)
            ->where('message', '')
            ->get(['public_key', 'signature']);

        $block->public_key = $generator->public_key;
        $block->reward_signature = $generator->signature;

        return $block;
    }

    public function findByHeight(int $height): self
    {
        return self::query()->where('height', $height)->first();
    }
}
