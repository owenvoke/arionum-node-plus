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
            Log::info("Reward DB insert failed");
            $db->rollback();
            $db->exec("UNLOCK TABLES");
            return false;
        }
        if ($masternodeWinner !== false && $height >= 80458 && $masternodeReward > 0) {
            $db->run(
                "UPDATE accounts SET balance=balance+:bal WHERE public_key=:pub",
                [":pub" => $masternodeWinner, ":bal" => $masternodeReward]
            );
            $bind = [
                ":id"         => Key::hexadecimalToAroBase58(hash("sha512", "mn".$hash.$height.$masternodeWinner)),
                ":public_key" => $publicKey,
                ":height"     => $height,
                ":block"      => $hash,
                ":dst"        => $acc->getAddress($masternodeWinner),
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
                // Rollback and exit if it fails
                Log::info("Masternode reward DB insert failed");

                $db->rollback();
                $db->exec("UNLOCK TABLES");
                return false;
            }
            $res = $this->resetFailedMasternodes($masternodeWinner, $height, $hash);
            if (!$res) {
                // Rollback and exit if it fails
                Log::info("Masternode log DB insert failed");
                $db->rollback();
                $db->exec("UNLOCK TABLES");
                return false;
            }
        }

        // parse the block's transactions and insert them to db
        $res = $this->parseBlock($hash, $height, $data, false, $bootstrapping);

        if (($height - 1) % 3 == 2 && $height >= 80000 && $height < 80458) {
            $this->blacklistMasternodes();
            $this->resetFailedMasternodes($publicKey, $height, $hash);
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

    public function resetFailedMasternodes($publicKey, $height, $hash)
    {
        $result = $this->masternodeLog($publicKey, $height, $hash);
        if ($result === 5) {
            return false;
        }

        if ($result) {
            $rez = $db->run(
                "UPDATE masternode SET last_won=:last_won,fails=0 WHERE public_key=:public_key",
                [":public_key" => $publicKey, ":last_won" => $height]
            );
            if ($rez != 1) {
                return false;
            }
        }
        return true;
    }

    public function masternodeLog($publicKey, $height, $blockHash)
    {
        $masternode = Masternode::query()
            ->where('public_key', $publicKey)
            ->first(['blacklist', 'last_won', 'fails']);

        if ($masternode->doesntExist()) {
            return false;
        }

        $id = Key::hexadecimalToAroBase58(hash('sha512', "resetfails-$blockHash-$height-$publicKey"));
        $message = $masternode['blacklist'].','.$masternode['last_won'].','.$masternode['fails'];

        return Transaction::query()->create([
            'id'         => $id,
            'block'      => $blockHash,
            'height'     => $height,
            'dst'        => $blockHash,
            'val'        => 0,
            'fee'        => 0,
            'sig'        => $blockHash,
            'version'    => 111,
            'message'    => $message,
            'date'       => Carbon::now(),
            'public_key' => $publicKey,
        ]) ? true : 5;
    }

    public function previous()
    {
        return self::query()->orderByDesc('height')->offset(1)->limit(1)->first();
    }

    // Calculates the difficulty / base target for a specific block.
    // The higher the difficulty number, the easier it is to win a block.
    public function difficulty()
    {
        if ($this->height == 10801 || ($this->height >= 80456 && $this->height < 80460)) {
            return '5555555555'; // Hard fork 10900 resistance, force new difficulty
        }

        // Last 20 blocks used to check the block times
        $limit = 20;
        if ($this->height < 20) {
            $limit = $this->height - 1;
        }

        // For the first 10 blocks, use the genesis difficulty
        if ($this->height < 10) {
            return $this->difficulty;
        }

        // Before masternodes hf
        if ($this->height < 80000) {
            // Elapsed time between the last 20 blocks
            $first = self::query()->orderByDesc('height')->offset($limit)->limit(1)->value('date');
            $time = $this->date - $first;

            // Average block time
            $result = ceil($time / $limit);
            Log::info('Block time: '.$result);


            // If larger than 200 sec, increase by 5%
            if ($result > 220) {
                $dif = bcmul($this->difficulty, 1.05);
            } elseif ($result < 260) {
                // If lower, decrease by 5%
                $dif = bcmul($this->difficulty, 0.95);
            } else {
                // Keep current difficulty
                $dif = $this->difficulty;
            }
        } elseif ($this->height >= 80458) {
            $type = $this->height % 2;
            $current = $db->row(
                "SELECT difficulty from blocks WHERE height<=:h ORDER by height DESC LIMIT 1,1",
                [":h" => $this->height]
            );
            $blks = 0;
            $totalTime = 0;
            $blk = $db->run(
                "SELECT `date`, height FROM blocks WHERE height<=:h  ORDER by height DESC LIMIT 20",
                [":h" => $this->height]
            );
            for ($i = 0; $i < 19; $i++) {
                $ctype = $blk[$i + 1]['height'] % 2;
                $time = $blk[$i]['date'] - $blk[$i + 1]['date'];
                if ($type != $ctype) {
                    continue;
                }
                $blks++;
                $totalTime += $time;
            }
            $result = ceil($totalTime / $blks);
            Log::info('Block time: '.$result);
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
            // Hard fork 80000, fix difficulty targeting

            $type = $this->height % 3;

            // For masternodes, we use gpu diff
            if ($type === 2) {
                return $this->difficulty;
            }

            $blks = 0;
            $totalTime = 0;
            $blk = self::query()->orderByDesc('height')->limit(60)->get(['date', 'height']);

            for ($i = 0; $i < 59; $i++) {
                $ctype = $blk[$i + 1]['height'] % 3;
                $time = $blk[$i]['date'] - $blk[$i + 1]['date'];
                if ($type !== $ctype) {
                    continue;
                }
                $blks++;
                $totalTime += $time;
            }

            $result = ceil($totalTime / $blks);
            Log::info('Block time: '.$result);

            // if larger than 260 sec, increase by 5%
            if ($result > 260) {
                $dif = bcmul($this->difficulty, 1.05);
            } elseif ($result < 220) {
                // if lower, decrease by 5%
                $dif = bcmul($this->difficulty, 0.95);
            } else {
                // keep current difficulty
                $dif = $this->difficulty;
            }
        }

        if (strpos($dif, '.') !== false) {
            $dif = substr($dif, 0, strpos($dif, '.'));
        }

        // Minimum difficulty
        if ($dif < 1000) {
            $dif = 1000;
        }

        // Maximum difficulty
        if ($dif > 9223372036854775800) {
            $dif = 9223372036854775800;
        }

        Log::info('Difficulty: '.$dif);

        return $dif;
    }

    public function maxTransactions()
    {
        $limit = $this->current()->height - 100;
        $average = self::query()->where('height', '>', $limit)->average('transactions');

        return ($average < 100) ? 100 : ceil($average * 1.1);
    }

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

    public function check()
    {
        // argon must have at least 20 chars
        if (strlen($this->argon) < 20) {
            Log::info('Invalid block argon - '.$this->argon);
            return false;
        }
        $acc = new Account();

        if ($this->date > time() + 30) {
            Log::info('Future block - '.$this->date.' '.$this->generator, 2);
            return false;
        }

        // generator's public key must be valid
        if (!$acc->validKey($data['public_key'])) {
            Log::info("Invalid public key - $data[public_key]");
            return false;
        }

        //difficulty should be the same as our calculation
        if ($data['difficulty'] != $this->difficulty()) {
            Log::info("Invalid difficulty - $data[difficulty] - ".$this->difficulty());
            return false;
        }

        //check the argon hash and the nonce to produce a valid block
        if (!$this->mine(
            $data['public_key'],
            $data['nonce'],
            $data['argon'],
            $data['difficulty'],
            0,
            0,
            $data['date']
        )
        ) {
            Log::info('Mine check failed');

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
            // Reward the masternode
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
                $mn_reward = round(0.33 * $reward, 8);
                $reward = round($reward - $mn_reward, 8);
                $reward = number_format($reward, 8, ".", "");
                $mn_reward = number_format($mn_reward, 8, ".", "");
                Log::info('MN Reward: '.$mn_reward);
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
        $result = $this->add(
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

        if ($result) {
            return true;
        }

        Log::info('Forge failed - Block->Add() failed');

        return false;
    }

    public function blacklistMasternodes(): bool
    {
        global $db;
        Log::info('Checking if there are masternodes to be blacklisted');
        $current = $this->current();
        if (($current['height'] - 1) % 3 != 2) {
            Log::info('Bad height');
            return false;
        }
        $last = $this->get($current['height'] - 1);
        $total_time = $current['date'] - $last['date'];
        Log::info("blacklist total time $total_time");
        if ($total_time <= 600 && $current['height'] < 80500) {
            return false;
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

        Log::info('We have masternodes to blacklist - '.$tem);

        $bans = Masternode::query()
            ->where('status', 1)
            ->where('blacklist', $current->height)
            ->where('height', $last['height'] - 360)
            ->orderBy('last_won')
            ->orderBy('public_key')
            ->offset(0)
            ->limit($tem)
            ->get(['public_key', 'blacklist', 'fails', 'last_won']);

        Log::info(json_encode($bans));

        $i = 0;
        foreach ($bans as $ban) {
            $this->masternodeLog($ban->public_key, $current->height, $current->id);
            Log::info("Blacklisting masternode - $i $ban[public_key]");
            $btime = 10;
            if ($current['height'] > 83000) {
                $btime = 360;
            }

            $masternode = Masternode::query()->where('public_key', $ban->public_key);
            $masternode->update(['blacklist' => $current->height + (($ban['fails'] + 1) * $btime)]);
            $masternode->increment('fails');

            $i++;
        }

        return true;
    }

    // check if the arguments are good for mining a specific block
    public function mine($publicKey, $nonce, $argon, $difficulty = 0, $currentId = 0, $currentHeight = 0, $time = 0)
    {
        // Invalid future blocks
        if ($time > time() + 30) {
            return false;
        }


        // If no id is specified, we use the current
        if ($currentId === 0 || $currentHeight === 0) {
            $current = $this->current();
            $currentId = $current['id'];
            $currentHeight = $current['height'];
        }

        Log::info('Block Timestamp '.$time);

        if ($time == 0) {
            $time = time();
        }

        // Get the current difficulty if empty
        if ($difficulty === 0) {
            $difficulty = $this->difficulty();
        }

        if (empty($publicKey)) {
            Log::info('Empty public key', 1);
            return false;
        }

        if ($currentHeight < 80000) {
            // The argon parameters are hardcoded to avoid any exploits
            if ($currentHeight > 10800) {
                Log::info('Block below 80000 but after 10800, using 512MB argon');
                $argon = '$argon2i$v=19$m=524288,t=1,p=1'.$argon; //10800 block hard fork - resistance against gpu
            } else {
                Log::info('Block below 10800, using 16MB argon');
                $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
            }
        } elseif ($currentHeight >= 80458) {
            if ($currentHeight % 2 == 0) {
                // CPU mining
                Log::info("CPU Mining - $currentHeight", 2);
                $argon = '$argon2i$v=19$m=524288,t=1,p=1'.$argon;
            } else {
                // GPU mining
                Log::info("GPU Mining - $currentHeight", 2);
                $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
            }
        } else {
            Log::info("Block > 80000 - $currentHeight", 2);
            if ($currentHeight % 3 == 0) {
                // CPU mining
                Log::info("CPU Mining - $currentHeight", 2);
                $argon = '$argon2i$v=19$m=524288,t=1,p=1'.$argon;
            } elseif ($currentHeight % 3 == 1) {
                // GPU mining
                Log::info("GPU Mining - $currentHeight", 2);
                $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
            } else {
                Log::info("Masternode Mining - $currentHeight", 2);
                // Masternode

                // Fake time
                if ($time > time()) {
                    Log::info('Masternode block in the future - '.$time);

                    return false;
                }

                // Selecting the masternode winner in order
                $winner = Masternode::query()
                    ->where('status', 1)
                    ->where('blacklist', '<', $currentHeight)
                    ->where('height', '<', $currentHeight)
                    ->orderBy('last_won')
                    ->orderBy('public_key')
                    ->limit(1)
                    ->value('public_key');

                // If there are no active masternodes, give the block to gpu
                if (!$winner) {
                    Log::info("No active masternodes, reverting to gpu", 1);
                    $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
                } else {
                    Log::info("The first masternode winner should be $winner", 1);

                    // 4 minutes need to pass since last block
                    $lastTime = Block::query()->where('height', $currentHeight)->value('date');

                    if ($time - $lastTime < 240 && !config('arionum.app.testnet')) {
                        Log::info('4 minutes have not passed since the last block - '.$time);
                        return false;
                    }

                    if ($publicKey == $winner) {
                        return true;
                    }

                    // If 10 minutes have passed
                    // Try to give the block to the next masternode and do this every 10 minutes
                    Log::info('Last block time: '.$lastTime.', difference: '.($time - $lastTime));
                    if (($time - $lastTime > 600 && $currentHeight < 80500) ||
                        ($time - $lastTime > 360 && $currentHeight >= 80500)
                    ) {
                        Log::info('Current public_key '.$publicKey, 3);
                        if ($currentHeight >= 80500) {
                            $total_time = $time - $lastTime;
                            $total_time -= 360;
                            $tem = floor($total_time / 120) + 1;
                        } else {
                            $tem = floor(($time - $lastTime) / 600);
                        }

                        $winner = Masternode::query()
                            ->where('status', 1)
                            ->where('blacklist', '<', $currentHeight)
                            ->where('height', '<', $currentHeight)
                            ->orderBy('last_won')
                            ->orderBy('public_key')
                            ->offset($tem)
                            ->limit(1)
                            ->value('public_key');

                        Log::info('Moving to the next masternode - '.$tem.' - '.$winner);

                        // if all masternodes are dead, give the block to gpu
                        if ($winner === false || ($tem >= 5 && $currentHeight >= 80500)) {
                            Log::info('All masternodes failed, giving the block to gpu');
                            $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
                        } elseif ($winner === $publicKey) {
                            return true;
                        }

                        return false;
                    }

                    Log::info('A different masternode should win this block '.$publicKey.' - '.$winner);
                    return false;
                }
            }
        }

        // The hash base for argon
        $base = $publicKey.'-'.$nonce.'-'.$currentId.'-'.$difficulty;

        // Check argon's hash validity
        if (!password_verify($base, $argon)) {
            Log::info('Argon verify failed - '.$base.' - '.$argon);

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


                // Prepare the total balance
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
                $result = Account::query()
                    ->where('id', $id)
                    ->where('balance', $balance)
                    ->first();

                if ($result->doesntExist()) {
                    Log::info('Not enough balance for transaction - '.$id);

                    return false;
                }
            }
        }

        // If the test argument is false, add the transactions to the blockchain
        if (!$test) {
            foreach ($data as $d) {
                $res = $transaction->add($block, $height, $d);
                if ($res === false) {
                    return false;
                }
            }
        }

        return true;
    }

    public function pop($blocksToRemove = 1)
    {
        $current = $this->current();

        $this->deleteGreaterThan($current['height'] - $blocksToRemove + 1);
    }

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

    public function sign($generator, $height, $date, $nonce, $data, $key, $difficulty, $argon)
    {
        $json = json_encode($data);
        $info = "{$generator}-{$height}-{$date}-{$nonce}-{$json}-{$difficulty}-{$argon}";

        $signature = EllipticCurve::sign($info, $key);
        return $signature;
    }

    public function hash($public_key, $height, $date, $nonce, $data, $signature, $difficulty, $argon)
    {
        $json = json_encode($data);
        $hash = hash("sha512", "{$public_key}-{$height}-{$date}-{$nonce}-{$json}-{$signature}-{$difficulty}-{$argon}");

        return Key::hexadecimalToAroBase58($hash);
    }


    public function export()
    {
        $exportData = $this->toArray();

        $exportData['data'] = Transaction::query()
            ->where('version', '>', 0)
            ->where('block', $this->id)
            ->get();

        /** @var Transaction $generator */
        $generator = Transaction::query()
            ->where('version', 0)
            ->where('block', $this->id)
            ->where('message', '')
            ->get(['public_key', 'signature']);

        $exportData['public_key'] = $generator->public_key;
        $exportData['reward_signature'] = $generator->signature;

        return $exportData;
    }

    public function findByHeight(int $height): self
    {
        return self::query()->where('height', $height)->first();
    }
}
